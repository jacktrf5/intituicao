<?php
// CONFIGURAÇÃO DO BANCO
$host = '127.0.0.1';
$dbname = 'instituição';
$username = 'root';
$password = '';

// VERIFICAR SE É PARA CONFIGURAR O SISTEMA
if (isset($_GET['configurar'])) {
    configurarSistema();
    exit;
}

// VERIFICAR SE É PARA EXCLUIR LEGISLAÇÃO
if (isset($_GET['excluir'])) {
    excluirLegislacao();
    exit;
}

// VERIFICAR SE É PARA EDITAR LEGISLAÇÃO
if (isset($_GET['editar'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        salvarEdicaoLegislacao();
    } else {
        mostrarFormularioEdicao();
    }
    exit;
}

// FUNÇÃO PARA CONFIGURAR O SISTEMA
function configurarSistema() {
    global $host, $username, $password;
    
    echo "<h1>🔧 CONFIGURAÇÃO DO SISTEMA</h1>";
    
    try {
        // Conectar ao MySQL
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<p style='color: green;'>✅ Conectado ao MySQL</p>";
        
        // Criar banco
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `instituição` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `instituição`");
        echo "<p style='color: green;'>✅ Banco de dados criado</p>";
        
        // Criar tabela instituicao
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS instituicao (
                id_unidade INT PRIMARY KEY,
                nome_unidade VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p style='color: green;'>✅ Tabela 'instituicao' criada</p>";
        
        // Inserir unidades
        $unidades = [
            [1, 'JFAL'],
            [2, 'JFCE'],
            [3, 'JFPB'],
            [4, 'JFPE'],
            [5, 'JFRN'],
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO instituicao (id_unidade, nome_unidade) VALUES (?, ?)");
        $inseridas = 0;
        
        foreach ($unidades as $unidade) {
            $stmt->execute([$unidade[0], $unidade[1]]);
            $inseridas++;
            echo "<p style='color: green;'>✅ {$unidade[1]}</p>";
        }
        
        // Criar tabela legislacao
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS legislacao (
                id_legislacao INT PRIMARY KEY AUTO_INCREMENT,
                id_unidade INT NOT NULL,
                descricao_legislacao VARCHAR(30) NOT NULL,
                data_legislacao DATE NOT NULL,
                url_legislacao VARCHAR(50) NOT NULL,
                data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p style='color: green;'>✅ Tabela 'legislacao' criada</p>";
        
        echo "<h1 style='color: green;'>🎉 SISTEMA CONFIGURADO COM SUCESSO!</h1>";
        echo "<p><a href='index.php'>👉 CLIQUE AQUI PARA IR PARA O SISTEMA</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ ERRO: " . $e->getMessage() . "</p>";
    }
}

// FUNÇÃO PARA EXCLUIR LEGISLAÇÃO
function excluirLegislacao() {
    global $host, $dbname, $username, $password;
    
    if (isset($_GET['id'])) {
        $id_legislacao = (int)$_GET['id'];
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // PRIMEIRO: Verificar se existe a tabela organograma_unidades e remover as referências
            $tabela_existe = $pdo->query("SHOW TABLES LIKE 'organograma_unidades'")->fetch();
            
            if ($tabela_existe) {
                // Remover as referências na tabela organograma_unidades primeiro
                $sql_remover_ref = "DELETE FROM organograma_unidades WHERE id_legislacao = ?";
                $stmt_ref = $pdo->prepare($sql_remover_ref);
                $stmt_ref->execute([$id_legislacao]);
            }
            
            // AGORA: Excluir a legislação
            $sql = "DELETE FROM legislacao WHERE id_legislacao = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_legislacao]);
            
            if ($stmt->rowCount() > 0) {
                echo "<script>
                    alert('✅ Legislação excluída com sucesso!');
                    window.location.href = 'index.php';
                </script>";
            } else {
                echo "<script>
                    alert('❌ Legislação não encontrada!');
                    window.location.href = 'index.php';
                </script>";
            }
            
        } catch (Exception $e) {
            // Se ainda der erro, tentar método alternativo
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                tentarExclusaoAlternativa($id_legislacao);
            } else {
                echo "<script>
                    alert('❌ Erro ao excluir: " . addslashes($e->getMessage()) . "');
                    window.location.href = 'index.php';
                </script>";
            }
        }
    }
}

// FUNÇÃO ALTERNATIVA PARA EXCLUSÃO
function tentarExclusaoAlternativa($id_legislacao) {
    global $host, $dbname, $username, $password;
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Método 1: Tentar desabilitar temporariamente as chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $sql = "DELETE FROM legislacao WHERE id_legislacao = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_legislacao]);
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        if ($stmt->rowCount() > 0) {
            echo "<script>
                alert('✅ Legislação excluída com sucesso!');
                window.location.href = 'index.php';
            </script>";
        } else {
            echo "<script>
                alert('❌ Legislação não encontrada!');
                window.location.href = 'index.php';
            </script>";
        }
        
    } catch (Exception $e) {
        echo "<script>
            alert('❌ Não foi possível excluir: Esta legislação está vinculada a outras tabelas.');
            window.location.href = 'index.php';
        </script>";
    }
}

// FUNÇÃO PARA MOSTRAR FORMULÁRIO DE EDIÇÃO
function mostrarFormularioEdicao() {
    global $host, $dbname, $username, $password;
    
    if (!isset($_GET['id'])) {
        echo "<script>alert('❌ ID não especificado!'); window.location.href = 'index.php';</script>";
        exit;
    }
    
    $id_legislacao = (int)$_GET['id'];
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Buscar dados da legislação
        $sql = "SELECT l.*, i.nome_unidade FROM legislacao l 
                LEFT JOIN instituicao i ON l.id_unidade = i.id_unidade 
                WHERE l.id_legislacao = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_legislacao]);
        $legislacao = $stmt->fetch();
        
        if (!$legislacao) {
            echo "<script>alert('❌ Legislação não encontrada!'); window.location.href = 'index.php';</script>";
            exit;
        }
        
        // Buscar unidades para o select
        $unidades = $pdo->query("SELECT id_unidade, nome_unidade FROM instituicao ORDER BY id_unidade")->fetchAll();
        
        // Formulário de edição
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Editar Legislação</title>
            <style>
                body { font-family: Arial; margin: 20px; background: #f0f2f5; }
                .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { text-align: center; margin-bottom: 20px; }
                .form-group { margin: 15px 0; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
                .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-primary { background: #007bff; color: white; }
                .btn-secondary { background: #6c757d; color: white; }
                .btn:hover { opacity: 0.9; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>✏️ Editar Legislação</h1>
                <form method="POST" action="index.php?editar=1&id=' . $id_legislacao . '">
                    <input type="hidden" name="id_legislacao" value="' . $id_legislacao . '">
                    
                    <div class="form-group">
                        <label>Unidade:</label>
                        <select name="id_unidade" required>';
        
        foreach ($unidades as $unidade) {
            $selected = $unidade['id_unidade'] == $legislacao['id_unidade'] ? 'selected' : '';
            echo '<option value="' . $unidade['id_unidade'] . '" ' . $selected . '>' . 
                 $unidade['id_unidade'] . ' - ' . $unidade['nome_unidade'] . '</option>';
        }
        
        echo '</select>
                    </div>

                    <div class="form-group">
                        <label>Descrição:</label>
                        <input type="text" name="descricao_legislacao" value="' . htmlspecialchars($legislacao['descricao_legislacao']) . '" maxlength="30" required>
                    </div>

                    <div class="form-group">
                        <label>Data:</label>
                        <input type="date" name="data_legislacao" value="' . $legislacao['data_legislacao'] . '" required>
                    </div>

                    <div class="form-group">
                        <label>URL:</label>
                        <input type="url" name="url_legislacao" value="' . htmlspecialchars($legislacao['url_legislacao']) . '" maxlength="50" required>
                    </div>

                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                        <a href="index.php" class="btn btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            </div>
        </body>
        </html>';
        
    } catch (Exception $e) {
        echo "<script>alert('❌ Erro: " . addslashes($e->getMessage()) . "'); window.location.href = 'index.php';</script>";
    }
}

// FUNÇÃO PARA SALVAR EDIÇÃO
function salvarEdicaoLegislacao() {
    global $host, $dbname, $username, $password;
    
    if (!isset($_POST['id_legislacao'])) {
        echo "<script>alert('❌ ID não especificado!'); window.location.href = 'index.php';</script>";
        exit;
    }
    
    $id_legislacao = (int)$_POST['id_legislacao'];
    $id_unidade = (int)$_POST['id_unidade'];
    $descricao = trim($_POST['descricao_legislacao']);
    $data = $_POST['data_legislacao'];
    $url = trim($_POST['url_legislacao']);
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE legislacao SET 
                id_unidade = ?, 
                descricao_legislacao = ?, 
                data_legislacao = ?, 
                url_legislacao = ? 
                WHERE id_legislacao = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_unidade, $descricao, $data, $url, $id_legislacao]);
        
        if ($stmt->rowCount() > 0) {
            echo "<script>
                alert('✅ Legislação atualizada com sucesso!');
                window.location.href = 'index.php';
            </script>";
        } else {
            echo "<script>
                alert('⚠️ Nenhuma alteração foi realizada.');
                window.location.href = 'index.php';
            </script>";
        }
        
    } catch (Exception $e) {
        echo "<script>
            alert('❌ Erro ao atualizar: " . addslashes($e->getMessage()) . "');
            window.location.href = 'index.php?editar=1&id=' + $id_legislacao;
        </script>";
    }
}

// LÓGICA PRINCIPAL DA APLICAÇÃO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Se não conectar, mostrar link para configuração
    mostrarPaginaErro();
    exit;
}

// VARIÁVEIS
$sucesso = '';
$erro = '';
$unidades = [];

// BUSCAR UNIDADES
try {
    $unidades = $pdo->query("SELECT id_unidade, nome_unidade FROM instituicao ORDER BY id_unidade")->fetchAll();
} catch (Exception $e) {
    $erro = "❌ Erro ao carregar unidades";
}

// PROCESSAR FORMULÁRIO DE CADASTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $id_unidade = (int)$_POST['id_unidade'];
    $descricao = trim($_POST['descricao_legislacao']);
    $data = $_POST['data_legislacao'];
    $url = trim($_POST['url_legislacao']);

    if (!$id_unidade || !$descricao || !$data || !$url) {
        $erro = "⚠️ Preencha todos os campos.";
    } else {
        try {
            $sql = "INSERT INTO legislacao (id_unidade, descricao_legislacao, data_legislacao, url_legislacao) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_unidade, $descricao, $data, $url]);
            $sucesso = "✅ Legislação cadastrada com sucesso!";
        } catch (Exception $e) {
            $erro = "❌ Erro: " . $e->getMessage();
        }
    }
}

// BUSCAR LEGISLAÇÕES
$legislacoes = [];
try {
    $legislacoes = $pdo->query("
        SELECT l.*, i.nome_unidade 
        FROM legislacao l 
        LEFT JOIN instituicao i ON l.id_unidade = i.id_unidade 
        ORDER BY l.data_legislacao DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Ignora erro
}

// FUNÇÃO PARA MOSTRAR PÁGINA DE ERRO
function mostrarPaginaErro() {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Sistema de Legislação</title>
        <style>
            body { font-family: Arial; margin: 50px; background: #f8f9fa; }
            .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
            h1 { color: #dc3545; }
            .btn { background: #007bff; color: white; padding: 15px 30px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>⚠️ Sistema Não Configurado</h1>
            <p>O banco de dados não está configurado.</p>
            <a href="index.php?configurar=1" class="btn">🔧 CONFIGURAR SISTEMA</a>
        </div>
    </body>
    </html>';
}

// SE NÃO HÁ UNIDADES, MOSTRAR ERRO
if (empty($unidades)) {
    mostrarPaginaErro();
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sistema Legislação</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f0f2f5; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 20px; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* CARDS */
        .cards { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; }
        .card { 
            background: white; 
            border: 2px solid #ddd; 
            border-radius: 5px; 
            padding: 15px; 
            cursor: pointer; 
            flex: 1; 
            min-width: 150px; 
            text-align: center;
        }
        .card:hover { border-color: #007bff; }
        .card.selected { border-color: #28a745; background: #f8fff9; }
        .card-id { font-size: 18px; font-weight: bold; color: #007bff; }
        .card-nome { font-size: 14px; color: #333; margin-top: 5px; }
        
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        
        /* BOTÕES AÇÕES */
        .btn-editar { 
            background: #ffc107; 
            color: #212529; 
            padding: 8px 12px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-editar:hover { background: #e0a800; }
        
        .btn-excluir { 
            background: #dc3545; 
            color: white; 
            padding: 8px 12px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-excluir:hover { background: #c82333; }
        
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        .acoes { text-align: center; width: 150px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Sistema de Legislação</h1>
        
        <?php if ($sucesso): ?>
            <div class="alert success"><?= $sucesso ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert error"><?= $erro ?></div>
        <?php endif; ?>

        <h2>📝 Cadastrar Legislação</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Selecione a Unidade:</label>
                <div class="cards">
                    <?php foreach ($unidades as $unidade): ?>
                        <div class="card" onclick="selectUnit(<?= $unidade['id_unidade'] ?>)">
                            <div class="card-id">#<?= $unidade['id_unidade'] ?></div>
                            <div class="card-nome"><?= $unidade['nome_unidade'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="id_unidade" id="id_unidade" required>
            </div>

            <div class="form-group">
                <label>Descrição:</label>
                <input type="text" name="descricao_legislacao" maxlength="30" required>
            </div>

            <div class="form-group">
                <label>Data:</label>
                <input type="date" name="data_legislacao" required value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label>URL:</label>
                <input type="url" name="url_legislacao" maxlength="50" required>
            </div>

            <button type="submit" name="cadastrar">Cadastrar</button>
        </form>

        <h2>📜 Legislações Cadastradas</h2>
        <?php if ($legislacoes): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Unidade</th>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th>URL</th>
                        <th class="acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($legislacoes as $l): ?>
                    <tr>
                        <td><?= $l['id_legislacao'] ?></td>
                        <td><?= $l['id_unidade'] ?> - <?= $l['nome_unidade'] ?></td>
                        <td><?= $l['descricao_legislacao'] ?></td>
                        <td><?= date('d/m/Y', strtotime($l['data_legislacao'])) ?></td>
                        <td>
                            <?php if (!empty($l['url_legislacao'])): ?>
                                <a href="<?= $l['url_legislacao'] ?>" target="_blank">🔗 Acessar</a>
                            <?php else: ?>
                                <span style="color: #999;">Sem URL</span>
                            <?php endif; ?>
                        </td>
                        <td class="acoes">
                            <a href="index.php?editar=1&id=<?= $l['id_legislacao'] ?>" class="btn-editar">
                                ✏️ Editar
                            </a>
                            <button class="btn-excluir" onclick="confirmarExclusao(<?= $l['id_legislacao'] ?>)">
                                🗑️ Excluir
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhuma legislação cadastrada.</p>
        <?php endif; ?>
    </div>

    <script>
        function selectUnit(unitId) {
            // Remove seleção anterior
            document.querySelectorAll('.card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Adiciona seleção atual
            event.currentTarget.classList.add('selected');
            
            // Define o valor no campo hidden
            document.getElementById('id_unidade').value = unitId;
        }
        
        function confirmarExclusao(id) {
            if (confirm('⚠️ Tem certeza que deseja excluir esta legislação?\nEsta ação não pode ser desfeita.')) {
                window.location.href = 'index.php?excluir=1&id=' + id;
            }
        }
    </script>
</body>
</html>