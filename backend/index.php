<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/Conexion.php";

// ================= CONFIG =================
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'tu_secreto_super_seguro_cambiar_en_produccion');
define('JWT_EXPIRATION', 3600);

$app = AppFactory::create();
$app->setBasePath('/API_Inventra');
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// ================= CORS =================
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Origin, X-Requested-With')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
$app->add(function (Request $request, RequestHandlerInterface $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Origin, X-Requested-With')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// ================= DB =================
$conn = Conexion::getPDO();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ================= HELPERS =================
function jsonResponse(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

function authenticate(Request $request): ?array {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) return null;
    try {
        $decoded = JWT::decode($matches[1], new Key(JWT_SECRET, 'HS256'));
        return (array)$decoded;
    } catch (Throwable $e) { return null; }
}

function fetchUserWithCompany(PDO $conn, int $userId): ?array {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.role, u.company_id, c.name AS company_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function tokenForUser(array $user): string {
    $payload = [
        'user_id'      => (int)$user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'company_id'   => $user['company_id'] !== null ? (int)$user['company_id'] : null,
        'company_name' => $user['company_name'] ?? null,
        'iat'          => time(),
        'exp'          => time() + JWT_EXPIRATION
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

// Aplica invitaciones pendientes (por email) si el usuario aún no tiene empresa
function applyPendingInvitations(PDO $conn, array $user): array {
    if (!empty($user['company_id'])) return $user;
    $stmt = $conn->prepare("
        SELECT * FROM invitations
        WHERE email = :email AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([':email' => $user['email']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) return $user;

    $upd = $conn->prepare("UPDATE users SET company_id = :cid, role = :role WHERE id = :uid");
    $upd->execute([':cid' => $inv['company_id'], ':role' => $inv['role'], ':uid' => $user['id']]);

    $mark = $conn->prepare("UPDATE invitations SET status='accepted', accepted_at=NOW() WHERE id = :iid");
    $mark->execute([':iid' => $inv['id']]);

    return fetchUserWithCompany($conn, (int)$user['id']) ?? $user;
}

// ================= MIDDLEWARE =================
function requireAuth(): callable {
    return function (Request $request, RequestHandlerInterface $handler) {
        $user = authenticate($request);
        if (!$user) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error'=>true,'message'=>'Token inválido o no proporcionado'], 401);
        }
        return $handler->handle($request->withAttribute('user', $user));
    };
}

function requireRole(array $allowedRoles): callable {
    return function (Request $request, RequestHandlerInterface $handler) use ($allowedRoles) {
        $user = $request->getAttribute('user');
        if (!$user || !in_array($user['role'] ?? '', $allowedRoles, true)) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error'=>true,'message'=>'Acceso denegado'], 403);
        }
        return $handler->handle($request);
    };
}

function requireCompany(): callable {
    return function (Request $request, RequestHandlerInterface $handler) {
        $user = $request->getAttribute('user');
        if (empty($user['company_id'])) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, [
                'error' => true,
                'message' => 'Sin empresa asignada. Pide a un admin que te invite.',
                'code'  => 'NO_COMPANY'
            ], 403);
        }
        return $handler->handle($request);
    };
}

// ================= LÓGICA VENTA =================
function createSale(PDO $conn, int $companyId, int $productId, int $userId, int $quantity): array {
    if ($quantity <= 0) throw new Exception("La cantidad debe ser mayor a 0");
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT price, stock FROM products WHERE id = :id AND company_id = :cid FOR UPDATE");
        $stmt->execute([':id' => $productId, ':cid' => $companyId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) throw new Exception("Producto no encontrado en tu empresa");
        if ((int)$product['stock'] < $quantity) throw new Exception("Stock insuficiente. Disponible: " . $product['stock']);

        $unitPrice  = (float)$product['price'];
        $totalPrice = $unitPrice * $quantity;

        $stmt = $conn->prepare("
            INSERT INTO sales (company_id, product_id, user_id, quantity, unit_price, total_price)
            VALUES (:cid, :pid, :uid, :qty, :uprice, :total)
        ");
        $stmt->execute([
            ':cid'    => $companyId,
            ':pid'    => $productId,
            ':uid'    => $userId,
            ':qty'    => $quantity,
            ':uprice' => $unitPrice,
            ':total'  => $totalPrice
        ]);
        $saleId = $conn->lastInsertId();

        $stmt = $conn->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid");
        $stmt->execute([':qty' => $quantity, ':pid' => $productId]);

        $conn->commit();
        return ['success'=>true, 'sale_id'=>(int)$saleId, 'total_price'=>$totalPrice];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}

// ================= DOCS =================
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'success' => true,
        'name'    => 'Inventra API · Multi-empresa',
        'version' => '3.0.0',
        'roles'   => [
            'admin'   => 'Crea una empresa al registrarse y la gestiona; invita a manager/user.',
            'manager' => 'Necesita ser invitado por un admin. Crea/edita productos de la empresa, registra ventas, ve analítica.',
            'user'    => 'Necesita ser invitado por un admin. Solo puede ver productos y registrar ventas.'
        ],
        'endpoints' => [
            'POST /auth/register     {name,email,password,role,company_name?}',
            'POST /auth/login        {email,password}',
            'GET  /me',
            'GET  /company',
            'GET  /company/members           (admin)',
            'DELETE /company/members/{id}    (admin)',
            'GET  /company/invitations       (admin)',
            'POST /company/invitations       (admin) {email,role}',
            'DELETE /company/invitations/{id} (admin)',
            'GET  /products                  (cualquier rol con empresa)',
            'POST /products                  (admin/manager)',
            'PUT  /products/{id}             (admin/manager)',
            'DELETE /products/{id}           (admin)',
            'POST /sales                     (cualquier rol con empresa)',
            'GET  /sales                     (cualquier rol con empresa)',
            'GET  /expenses                  (admin/manager)',
            'POST /expenses                  (admin/manager) {description,amount,expense_date,category?}',
            'PUT  /expenses/{id}             (admin/manager)',
            'DELETE /expenses/{id}           (admin)',
            'GET  /analytics/summary         (admin/manager)'
        ]
    ]);
});

// ================= AUTH =================
$app->post('/auth/register', function (Request $request, Response $response) use ($conn) {
    $data = $request->getParsedBody() ?? [];
    foreach (['name','email','password'] as $f) {
        if (empty($data[$f])) return jsonResponse($response, ['error'=>true,'message'=>"Falta campo: $f"], 400);
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return jsonResponse($response, ['error'=>true,'message'=>'Email inválido'], 400);
    }
    $role = in_array(($data['role'] ?? ''), ['admin','manager','user'], true) ? $data['role'] : 'user';

    if ($role === 'admin' && empty($data['company_name'])) {
        return jsonResponse($response, ['error'=>true,'message'=>'Para registrarte como admin debes indicar el nombre de la empresa'], 400);
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    if ($stmt->fetch()) return jsonResponse($response, ['error'=>true,'message'=>'Email ya registrado'], 409);

    $hashed = password_hash((string)$data['password'], PASSWORD_DEFAULT);

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (:n,:e,:h,:r)");
        $stmt->execute([':n'=>$data['name'], ':e'=>$data['email'], ':h'=>$hashed, ':r'=>$role]);
        $userId = (int)$conn->lastInsertId();

        if ($role === 'admin') {
            $stmt = $conn->prepare("INSERT INTO companies (name, admin_id) VALUES (:n, :a)");
            $stmt->execute([':n'=>$data['company_name'], ':a'=>$userId]);
            $companyId = (int)$conn->lastInsertId();
            $upd = $conn->prepare("UPDATE users SET company_id = :c WHERE id = :u");
            $upd->execute([':c'=>$companyId, ':u'=>$userId]);
        }
        $conn->commit();

        $user = fetchUserWithCompany($conn, $userId);
        if ($user) $user = applyPendingInvitations($conn, $user);

        return jsonResponse($response, [
            'success' => true,
            'user_id' => $userId,
            'company_assigned' => !empty($user['company_id'])
        ], 201);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        return jsonResponse($response, ['error'=>true,'message'=>'No se pudo crear: '.$e->getMessage()], 500);
    }
});

$app->post('/auth/login', function (Request $request, Response $response) use ($conn) {
    $data = $request->getParsedBody() ?? [];
    if (empty($data['email']) || empty($data['password'])) {
        return jsonResponse($response, ['error'=>true,'message'=>'Email y password son obligatorios'], 400);
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email'=>$data['email']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify((string)$data['password'], (string)$row['password_hash'])) {
        return jsonResponse($response, ['error'=>true,'message'=>'Credenciales inválidas'], 401);
    }

    $user = fetchUserWithCompany($conn, (int)$row['id']);
    $user = applyPendingInvitations($conn, $user);

    return jsonResponse($response, [
        'success' => true,
        'token'   => tokenForUser($user),
        'user'    => [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'company_id'   => $user['company_id'] !== null ? (int)$user['company_id'] : null,
            'company_name' => $user['company_name']
        ]
    ]);
});

// ================= PROTECTED =================
$app->group('', function (RouteCollectorProxy $group) use ($conn) {

    // ------ ME ------
    $group->get('/me', function (Request $request, Response $response) use ($conn) {
        $jwt  = $request->getAttribute('user');
        $user = fetchUserWithCompany($conn, (int)$jwt['user_id']);
        if (!$user) return jsonResponse($response, ['error'=>true,'message'=>'Usuario no existe'], 404);

        // Aplicar invitaciones pendientes y devolver token nuevo si cambia
        $before = $user['company_id'];
        $user = applyPendingInvitations($conn, $user);
        $payload = [
            'user' => [
                'id'           => (int)$user['id'],
                'name'         => $user['name'],
                'email'        => $user['email'],
                'role'         => $user['role'],
                'company_id'   => $user['company_id'] !== null ? (int)$user['company_id'] : null,
                'company_name' => $user['company_name']
            ]
        ];
        if ($before !== $user['company_id']) {
            $payload['token'] = tokenForUser($user);
        }
        return jsonResponse($response, $payload);
    });

    // ------ COMPANY ------
    $group->get('/company', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        if (empty($jwt['company_id'])) return jsonResponse($response, ['company'=>null]);
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.created_at, u.id AS admin_id, u.name AS admin_name, u.email AS admin_email
            FROM companies c
            JOIN users u ON c.admin_id = u.id
            WHERE c.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $jwt['company_id']]);
        return jsonResponse($response, ['company' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
    });

    // Solo admin: miembros / invitaciones
    $group->get('/company/members', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("
            SELECT id, name, email, role, created_at
            FROM users WHERE company_id = :cid
            ORDER BY role = 'admin' DESC, name ASC
        ");
        $stmt->execute([':cid' => $jwt['company_id']]);
        return jsonResponse($response, ['members' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    })->add(requireRole(['admin']))->add(requireCompany());

    $group->delete('/company/members/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $uid = (int)$args['id'];
        if ($uid === (int)$jwt['user_id']) {
            return jsonResponse($response, ['error'=>true,'message'=>'No puedes expulsarte a ti mismo'], 400);
        }
        $stmt = $conn->prepare("UPDATE users SET company_id=NULL, role='user' WHERE id = :u AND company_id = :c");
        $stmt->execute([':u'=>$uid, ':c'=>$jwt['company_id']]);
        return jsonResponse($response, ['success'=>true]);
    })->add(requireRole(['admin']))->add(requireCompany());

    $group->get('/company/invitations', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("
            SELECT id, email, role, status, created_at, accepted_at
            FROM invitations
            WHERE company_id = :cid
            ORDER BY status = 'pending' DESC, created_at DESC
        ");
        $stmt->execute([':cid' => $jwt['company_id']]);
        return jsonResponse($response, ['invitations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    })->add(requireRole(['admin']))->add(requireCompany());

    $group->post('/company/invitations', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return jsonResponse($response, ['error'=>true,'message'=>'Email inválido'], 400);
        }
        $role = in_array(($data['role'] ?? ''), ['manager','user'], true) ? $data['role'] : 'user';
        $email = strtolower(trim($data['email']));

        // Si el usuario ya existe sin empresa → asignar directamente
        $stmt = $conn->prepare("SELECT id, company_id FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (!empty($existing['company_id']) && (int)$existing['company_id'] !== (int)$jwt['company_id']) {
                return jsonResponse($response, ['error'=>true,'message'=>'Este usuario ya pertenece a otra empresa'], 409);
            }
            if (empty($existing['company_id'])) {
                $upd = $conn->prepare("UPDATE users SET company_id = :c, role = :r WHERE id = :u");
                $upd->execute([':c'=>$jwt['company_id'], ':r'=>$role, ':u'=>$existing['id']]);
                // crea registro de invitación aceptada (auditoría)
                try {
                    $ins = $conn->prepare("
                        INSERT INTO invitations (company_id,email,role,status,created_by,accepted_at)
                        VALUES (:c,:e,:r,'accepted',:b,NOW())
                    ");
                    $ins->execute([':c'=>$jwt['company_id'], ':e'=>$email, ':r'=>$role, ':b'=>$jwt['user_id']]);
                } catch (Throwable $e) { /* uniq key, ignorable */ }
                return jsonResponse($response, ['success'=>true,'auto_assigned'=>true], 201);
            }
        }

        // Crear/actualizar invitación pendiente
        try {
            $ins = $conn->prepare("
                INSERT INTO invitations (company_id, email, role, created_by)
                VALUES (:c, :e, :r, :b)
                ON DUPLICATE KEY UPDATE role = VALUES(role), status='pending', accepted_at=NULL
            ");
            $ins->execute([':c'=>$jwt['company_id'], ':e'=>$email, ':r'=>$role, ':b'=>$jwt['user_id']]);
        } catch (Throwable $e) {
            return jsonResponse($response, ['error'=>true,'message'=>$e->getMessage()], 500);
        }
        return jsonResponse($response, ['success'=>true,'auto_assigned'=>false], 201);
    })->add(requireRole(['admin']))->add(requireCompany());

    $group->delete('/company/invitations/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("UPDATE invitations SET status='revoked' WHERE id = :i AND company_id = :c");
        $stmt->execute([':i'=>(int)$args['id'], ':c'=>$jwt['company_id']]);
        return jsonResponse($response, ['success'=>true]);
    })->add(requireRole(['admin']))->add(requireCompany());

    // ------ PRODUCTS (scope por company_id) ------
    $group->get('/products', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("
            SELECT p.*, u.name AS owner_name
            FROM products p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.company_id = :cid
            ORDER BY p.id DESC
        ");
        $stmt->execute([':cid' => $jwt['company_id']]);
        return jsonResponse($response, ['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    })->add(requireCompany());

    $group->post('/products', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        foreach (['name','price','cost','stock'] as $f) {
            if (!isset($data[$f])) return jsonResponse($response, ['error'=>true,'message'=>"Falta campo: $f"], 400);
        }
        $stmt = $conn->prepare("
            INSERT INTO products (company_id, user_id, name, description, image_url, price, cost, stock, category)
            VALUES (:cid, :uid, :name, :desc, :img, :price, :cost, :stock, :cat)
        ");
        $stmt->execute([
            ':cid'=>$jwt['company_id'], ':uid'=>$jwt['user_id'],
            ':name'=>$data['name'], ':desc'=>$data['description'] ?? null,
            ':img'=>$data['image_url'] ?? null, ':price'=>$data['price'],
            ':cost'=>$data['cost'], ':stock'=>$data['stock'],
            ':cat'=>$data['category'] ?? null
        ]);
        return jsonResponse($response, ['success'=>true,'product_id'=>(int)$conn->lastInsertId()], 201);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

    $group->put('/products/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $id = (int)$args['id'];

        $check = $conn->prepare("SELECT id FROM products WHERE id = :id AND company_id = :cid");
        $check->execute([':id'=>$id, ':cid'=>$jwt['company_id']]);
        if (!$check->fetch()) return jsonResponse($response, ['error'=>true,'message'=>'Producto no existe en tu empresa'], 404);

        $data = $request->getParsedBody() ?? [];
        $fields = []; $params = [':id'=>$id];
        foreach (['name','description','image_url','price','cost','stock','category'] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = :$f"; $params[":$f"] = $data[$f]; }
        }
        if (!$fields) return jsonResponse($response, ['error'=>true,'message'=>'Sin datos para actualizar'], 400);
        $stmt = $conn->prepare("UPDATE products SET ".implode(', ', $fields)." WHERE id = :id");
        $stmt->execute($params);
        return jsonResponse($response, ['success'=>true]);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

    $group->delete('/products/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("DELETE FROM products WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id'=>(int)$args['id'], ':cid'=>$jwt['company_id']]);
        if ($stmt->rowCount() === 0) return jsonResponse($response, ['error'=>true,'message'=>'No existe'], 404);
        return jsonResponse($response, ['success'=>true]);
    })->add(requireRole(['admin']))->add(requireCompany());

    // ------ SALES ------
    $group->post('/sales', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        if (empty($data['product_id']) || empty($data['quantity'])) {
            return jsonResponse($response, ['error'=>true,'message'=>'product_id y quantity son obligatorios'], 400);
        }
        try {
            $r = createSale($conn, (int)$jwt['company_id'], (int)$data['product_id'], (int)$jwt['user_id'], (int)$data['quantity']);
            return jsonResponse($response, $r, 201);
        } catch (Throwable $e) {
            return jsonResponse($response, ['error'=>true,'message'=>$e->getMessage()], 400);
        }
    })->add(requireCompany());

    $group->get('/sales', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("
            SELECT s.id, s.product_id, s.user_id, s.quantity, s.unit_price, s.total_price, s.created_at,
                   p.name AS product_name, u.name AS seller_name
            FROM sales s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.company_id = :cid
            ORDER BY s.created_at DESC
            LIMIT 500
        ");
        $stmt->execute([':cid'=>$jwt['company_id']]);
        return jsonResponse($response, ['sales' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    })->add(requireCompany());

    // ------ UPLOADS (imagen de producto) ------
    // Recibe multipart/form-data con campo "image". Guarda en /Tracker_Api/product_img/.
    // Devuelve la URL pública que se puede pegar en products.image_url.
    $group->post('/uploads/product-image', function (Request $request, Response $response) use ($conn) {
        $jwt   = $request->getAttribute('user');
        $files = $request->getUploadedFiles();
        if (empty($files['image'])) {
            return jsonResponse($response, ['error'=>true,'message'=>'Falta el campo image'], 400);
        }
        $file = $files['image'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return jsonResponse($response, ['error'=>true,'message'=>'Error al subir el archivo (código '.$file->getError().')'], 400);
        }
        $maxBytes = 5 * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            return jsonResponse($response, ['error'=>true,'message'=>'Imagen demasiado grande (máx 5 MB)'], 400);
        }

        $type = $file->getClientMediaType();
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($allowed[$type])) {
            return jsonResponse($response, ['error'=>true,'message'=>'Tipo no permitido. Usa JPG, PNG, WebP o GIF.'], 400);
        }

        // Carpeta destino: /public_html/API_Inventra/product_img/
        $uploadDir = __DIR__ . '/product_img/';

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                return jsonResponse($response, ['error'=>true,'message'=>'No se pudo crear el directorio: '.$uploadDir], 500);
            }
        }
        if (!is_writable($uploadDir)) {
            return jsonResponse($response, ['error'=>true,'message'=>'Sin permisos de escritura en: '.$uploadDir], 500);
        }

        $filename = sprintf(
            'c%d_%s_%s.%s',
            (int)$jwt['company_id'],
            date('YmdHis'),
            bin2hex(random_bytes(4)),
            $allowed[$type]
        );

        try {
            $file->moveTo($uploadDir . $filename);
        } catch (Throwable $e) {
            return jsonResponse($response, ['error'=>true,'message'=>'No se pudo guardar: '.$e->getMessage()], 500);
        }

        return jsonResponse($response, [
            'success'  => true,
            'url'      => 'https://ignaciosanchezyuste.es/API_Inventra/product_img/' . $filename,
            'filename' => $filename
        ], 201);
    })->add(requireCompany());

    // ------ EXPENSES (gastos de la empresa) ------
    // Solo admin/manager pueden listar, crear y editar.
    // Solo admin puede borrar.
    $group->get('/expenses', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("
            SELECT e.id, e.company_id, e.user_id, e.category, e.description,
                   e.amount, e.expense_date, e.created_at, e.updated_at,
                   u.name AS user_name
            FROM expenses e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.company_id = :cid
            ORDER BY e.expense_date DESC, e.id DESC
            LIMIT 1000
        ");
        $stmt->execute([':cid' => $jwt['company_id']]);
        return jsonResponse($response, ['expenses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

    $group->post('/expenses', function (Request $request, Response $response) use ($conn) {
        $jwt  = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        $description = trim((string)($data['description'] ?? ''));
        $amount      = isset($data['amount']) ? (float)$data['amount'] : -1;
        $expDate     = trim((string)($data['expense_date'] ?? ''));
        $category    = isset($data['category']) ? trim((string)$data['category']) : null;

        if ($description === '') return jsonResponse($response, ['error'=>true,'message'=>'La descripción es obligatoria'], 400);
        if ($amount < 0)          return jsonResponse($response, ['error'=>true,'message'=>'El importe debe ser >= 0'], 400);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expDate)) {
            return jsonResponse($response, ['error'=>true,'message'=>'Fecha inválida (YYYY-MM-DD)'], 400);
        }
        if ($category === '') $category = null;

        $stmt = $conn->prepare("
            INSERT INTO expenses (company_id, user_id, category, description, amount, expense_date)
            VALUES (:cid, :uid, :cat, :desc, :amt, :ed)
        ");
        $stmt->execute([
            ':cid'  => $jwt['company_id'],
            ':uid'  => $jwt['user_id'],
            ':cat'  => $category,
            ':desc' => $description,
            ':amt'  => $amount,
            ':ed'   => $expDate
        ]);
        $id = (int)$conn->lastInsertId();

        $sel = $conn->prepare("
            SELECT e.id, e.company_id, e.user_id, e.category, e.description,
                   e.amount, e.expense_date, e.created_at, e.updated_at,
                   u.name AS user_name
            FROM expenses e LEFT JOIN users u ON e.user_id = u.id
            WHERE e.id = :id LIMIT 1
        ");
        $sel->execute([':id' => $id]);
        return jsonResponse($response, [
            'success' => true,
            'expense' => $sel->fetch(PDO::FETCH_ASSOC)
        ], 201);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

    $group->put('/expenses/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $id  = (int)$args['id'];

        $check = $conn->prepare("SELECT id FROM expenses WHERE id = :id AND company_id = :cid");
        $check->execute([':id'=>$id, ':cid'=>$jwt['company_id']]);
        if (!$check->fetch()) return jsonResponse($response, ['error'=>true,'message'=>'Gasto no existe en tu empresa'], 404);

        $data = $request->getParsedBody() ?? [];
        $fields = []; $params = [':id'=>$id];

        if (array_key_exists('description', $data)) {
            $desc = trim((string)$data['description']);
            if ($desc === '') return jsonResponse($response, ['error'=>true,'message'=>'Descripción no puede estar vacía'], 400);
            $fields[] = 'description = :description';
            $params[':description'] = $desc;
        }
        if (array_key_exists('amount', $data)) {
            $amt = (float)$data['amount'];
            if ($amt < 0) return jsonResponse($response, ['error'=>true,'message'=>'Importe inválido'], 400);
            $fields[] = 'amount = :amount';
            $params[':amount'] = $amt;
        }
        if (array_key_exists('expense_date', $data)) {
            $ed = trim((string)$data['expense_date']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ed)) {
                return jsonResponse($response, ['error'=>true,'message'=>'Fecha inválida (YYYY-MM-DD)'], 400);
            }
            $fields[] = 'expense_date = :expense_date';
            $params[':expense_date'] = $ed;
        }
        if (array_key_exists('category', $data)) {
            $cat = trim((string)$data['category']);
            $fields[] = 'category = :category';
            $params[':category'] = $cat === '' ? null : $cat;
        }

        if (!$fields) return jsonResponse($response, ['error'=>true,'message'=>'Sin datos para actualizar'], 400);

        $stmt = $conn->prepare("UPDATE expenses SET ".implode(', ', $fields)." WHERE id = :id");
        $stmt->execute($params);

        $sel = $conn->prepare("
            SELECT e.id, e.company_id, e.user_id, e.category, e.description,
                   e.amount, e.expense_date, e.created_at, e.updated_at,
                   u.name AS user_name
            FROM expenses e LEFT JOIN users u ON e.user_id = u.id
            WHERE e.id = :id LIMIT 1
        ");
        $sel->execute([':id' => $id]);
        return jsonResponse($response, [
            'success' => true,
            'expense' => $sel->fetch(PDO::FETCH_ASSOC)
        ]);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

    $group->delete('/expenses/{id}', function (Request $request, Response $response, array $args) use ($conn) {
        $jwt = $request->getAttribute('user');
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id'=>(int)$args['id'], ':cid'=>$jwt['company_id']]);
        if ($stmt->rowCount() === 0) return jsonResponse($response, ['error'=>true,'message'=>'No existe'], 404);
        return jsonResponse($response, ['success'=>true]);
    })->add(requireRole(['admin']))->add(requireCompany());

    // ------ ANALYTICS ------
    $group->get('/analytics/summary', function (Request $request, Response $response) use ($conn) {
        $jwt = $request->getAttribute('user');
        $cid = (int)$jwt['company_id'];

        $r = $conn->prepare("
            SELECT SUM(s.total_price) AS revenue,
                   SUM(s.quantity * (s.unit_price - COALESCE(p.cost,0))) AS profit
            FROM sales s JOIN products p ON s.product_id = p.id
            WHERE s.company_id = :c
        ");
        $r->execute([':c'=>$cid]);
        $totals = $r->fetch(PDO::FETCH_ASSOC) ?: ['revenue'=>0,'profit'=>0];

        $c = $conn->prepare("SELECT COUNT(*) AS total_sales FROM sales WHERE company_id = :c");
        $c->execute([':c'=>$cid]);
        $count = $c->fetch(PDO::FETCH_ASSOC);

        $ex = $conn->prepare("
            SELECT
                COALESCE(SUM(amount), 0)             AS total_expenses,
                COUNT(*)                              AS total_expense_entries,
                COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount ELSE 0 END), 0) AS expenses_month
            FROM expenses WHERE company_id = :c
        ");
        $ex->execute([':c'=>$cid]);
        $exTotals = $ex->fetch(PDO::FETCH_ASSOC) ?: ['total_expenses'=>0,'total_expense_entries'=>0,'expenses_month'=>0];

        // Gastos por categoría (top)
        $catStmt = $conn->prepare("
            SELECT COALESCE(NULLIF(category,''), 'Sin categoría') AS category,
                   SUM(amount) AS total
            FROM expenses WHERE company_id = :c
            GROUP BY category
            ORDER BY total DESC
            LIMIT 12
        ");
        $catStmt->execute([':c'=>$cid]);

        $low = $conn->prepare("
            SELECT id, name, stock FROM products
            WHERE company_id = :c AND stock < 5
            ORDER BY stock ASC, id ASC
        ");
        $low->execute([':c'=>$cid]);

        $grossProfit = (float)($totals['profit'] ?? 0);
        $totalExpenses = (float)($exTotals['total_expenses'] ?? 0);
        $netProfit = $grossProfit - $totalExpenses;

        return jsonResponse($response, [
            'total_revenue'         => (float)($totals['revenue'] ?? 0),
            'total_profit'          => $grossProfit,           // beneficio bruto (revenue - cost)
            'total_expenses'        => $totalExpenses,
            'expenses_this_month'   => (float)($exTotals['expenses_month'] ?? 0),
            'total_expense_entries' => (int)($exTotals['total_expense_entries'] ?? 0),
            'net_profit'            => $netProfit,             // beneficio neto (bruto - gastos)
            'total_sales'           => (int)($count['total_sales'] ?? 0),
            'expenses_by_category'  => $catStmt->fetchAll(PDO::FETCH_ASSOC),
            'low_stock_products'    => $low->fetchAll(PDO::FETCH_ASSOC)
        ]);
    })->add(requireRole(['admin','manager']))->add(requireCompany());

})->add(requireAuth());

// ================= ERRORS =================
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setErrorHandler(\Slim\Exception\HttpNotFoundException::class,
    function (Request $request, Throwable $e, bool $d) use ($app) {
        $r = $app->getResponseFactory()->createResponse();
        $r->getBody()->write(json_encode(['error'=>true,'message'=>'Ruta no encontrada']));
        return $r->withHeader('Content-Type','application/json')
                 ->withHeader('Access-Control-Allow-Origin','*')->withStatus(404);
    });

$errorMiddleware->setErrorHandler(\Slim\Exception\HttpMethodNotAllowedException::class,
    function (Request $request, Throwable $e, bool $d) use ($app) {
        $r = $app->getResponseFactory()->createResponse();
        $r->getBody()->write(json_encode(['error'=>true,'message'=>'Método no permitido']));
        return $r->withHeader('Content-Type','application/json')
                 ->withHeader('Access-Control-Allow-Origin','*')->withStatus(405);
    });

$errorMiddleware->setDefaultErrorHandler(
    function (Request $request, Throwable $e, bool $d, bool $l, bool $ld) use ($app) {
        $r = $app->getResponseFactory()->createResponse();
        $r->getBody()->write(json_encode(['error'=>true,'message'=>$d ? $e->getMessage() : 'Error interno del servidor']));
        return $r->withHeader('Content-Type','application/json')
                 ->withHeader('Access-Control-Allow-Origin','*')->withStatus(500);
    });

$app->run();
