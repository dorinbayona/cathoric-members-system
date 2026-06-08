<?php
// Washa session ili mfumo uweze kutunza kumbukumbu ya waumini hata wakilogout
session_start();

// Mfumo sasa unaanza ukiwa tupu (Empty by default) bila mifano yoyote ya waumini au rekodi
if (!isset($_SESSION['members'])) {
    $_SESSION['members'] = [];
}
if (!isset($_SESSION['admin_credentials'])) {
    $_SESSION['admin_credentials'] = [
        'email' => 'admin@church.com',
        'password' => 'Doreen123'
    ];
}
if (!isset($_SESSION['contributions'])) {
    $_SESSION['contributions'] = [];
}
if (!isset($_SESSION['attendance'])) {
    $_SESSION['attendance'] = [];
}
if (!isset($_SESSION['feedback'])) {
    $_SESSION['feedback'] = [];
}
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = ["Karibuni kwenye Mfumo wa Parokia wetu mpya!"];
}

$message = "";

// CSV Export Logic (Save Reports)
if (isset($_GET['export'])) {
    if ($_GET['export'] == 'contributions') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Ripoti_ya_Zaka.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Jina la Muumini', 'Aina', 'Kiasi (TSH)', 'Tarehe']);
        foreach ($_SESSION['contributions'] as $con) {
            fputcsv($output, [$con['name'], $con['type'], $con['amount'], $con['date']]);
        }
        fclose($output);
        exit;
    }
    if ($_GET['export'] == 'attendance') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Ripoti_ya_Mahudhurio.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Tarehe na Muda Automatic', 'Jina la Muumini', 'Hali ya Hudhurio']);
        foreach ($_SESSION['attendance'] as $att) {
            fputcsv($output, [$att['datetime'], $att['name'], $att['status']]);
        }
        fclose($output);
        exit;
    }
}

// Form Handling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Usajili wa Muumini mpya
    if (isset($_POST['register'])) {
        $email_exists = false;
        foreach ($_SESSION['members'] as $member) {
            if ($member['email'] == $_POST['email']) {
                $email_exists = true;
                break;
            }
        }

        if ($email_exists) {
            $message = "<div class='alert danger'>Barua pepe hii ishatumika! Tumia nyingine au ingia (Login).</div>";
        } else {
            $new_user = [
                'id' => count($_SESSION['members']) + 1,
                'name' => $_POST['fullname'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'group' => $_POST['group'],
                'status' => 'Hai'
            ];
            $_SESSION['members'][] = $new_user;
            $message = "<div class='alert success'>Akaunti imetengenezwa kikamilifu! Sasa unaweza kuingia (Login).</div>";
        }
    }
    
    // 2. Kuingia Mfomoni (Login)
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        if ($email == $_SESSION['admin_credentials']['email'] && $password == $_SESSION['admin_credentials']['password']) {
            $_SESSION['role'] = 'admin';
            $_SESSION['user'] = 'Paroko / Admin';
            header("Location: ?page=admin_dashboard");
            exit;
        } else {
            $user_found = false;
            foreach ($_SESSION['members'] as $member) {
                if ($member['email'] == $email && $member['password'] == $password) {
                    $_SESSION['role'] = 'member';
                    $_SESSION['user'] = $member['name'];
                    $user_found = true;
                    header("Location: ?page=member_dashboard");
                    exit;
                }
            }
            if (!$user_found) {
                $message = "<div class='alert danger'>Barua Pepe au Neno la Siri sio sahihi!</div>";
            }
        }
    }

    // 3. Kusahau Neno la Siri (Forgot Password)
    if (isset($_POST['reset_password'])) {
        $email = $_POST['email'];
        $new_password = $_POST['new_password'];
        $updated = false;

        if ($email == $_SESSION['admin_credentials']['email']) {
            $_SESSION['admin_credentials']['password'] = $new_password;
            $updated = true;
        } else {
            foreach ($_SESSION['members'] as $key => $member) {
                if ($member['email'] == $email) {
                    $_SESSION['members'][$key]['password'] = $new_password;
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            $message = "<div class='alert success'>Neno la Siri limebadilishwa kikamilifu!</div>";
            $page = 'login';
        } else {
            $message = "<div class='alert danger'>Barua pepe hiyo haijasajiliwa!</div>";
        }
    }

    // 4. Admin Kuongeza Zaka ya Muumini
    if (isset($_POST['add_contribution'])) {
        $_SESSION['contributions'][] = [
            'id' => count($_SESSION['contributions']) + 1,
            'name' => $_POST['member_name'],
            'type' => 'Zaka',
            'amount' => intval($_POST['amount']),
            'date' => date('Y-m-d')
        ];
        $message = "<div class='alert success'>Mchango wa Zaka umesajiliwa kikamilifu!</div>";
    }

    // 5. Admin Kuweka Hudhurio Automatic
    if (isset($_POST['add_attendance'])) {
        $days = ['Sunday'=>'Jumapili', 'Monday'=>'Jumatatu', 'Tuesday'=>'Jumanne', 'Wednesday'=>'Jumatano', 'Thursday'=>'Alhamisi', 'Friday'=>'Ijumaa', 'Saturday'=>'Jumamosi'];
        $current_day = $days[date('l')];
        $formatted_datetime = $current_day . ", " . date('Y-m-d H:i');

        $_SESSION['attendance'][] = [
            'id' => count($_SESSION['attendance']) + 1,
            'datetime' => $formatted_datetime,
            'name' => $_POST['att_member_name'],
            'status' => $_POST['attendance_status']
        ];
        $message = "<div class='alert success'>Hudhurio limesajiliwa automatic na muda wa sasa!</div>";
    }

    // 6. Muumini Kutuma Maoni
    if (isset($_POST['send_feedback'])) {
        $_SESSION['feedback'][] = [
            'id' => count($_SESSION['feedback']) + 1,
            'name' => $_SESSION['user'],
            'message' => $_POST['feedback_text']
        ];
        $message = "<div class='alert success'>Maoni yako yamewasilishwa.</div>";
    }

    // 7. Admin Kutuma Taarifa (Broadcast)
    if (isset($_POST['send_notice'])) {
        $_SESSION['notifications'][] = $_POST['notice_text'];
        $message = "<div class='alert success'>Taarifa imetumwa kwa waumini wote!</div>";
    }
}

// Admin Action: Kufuta Maoni
if (isset($_GET['action']) && $_GET['action'] == 'delete_feedback' && isset($_GET['id'])) {
    foreach ($_SESSION['feedback'] as $key => $fb) {
        if ($fb['id'] == $_GET['id']) { unset($_SESSION['feedback'][$key]); break; }
    }
    header("Location: ?page=admin_dashboard"); exit;
}

// Admin Action: Kufuta Muumini
if (isset($_GET['action']) && $_GET['action'] == 'delete_member' && isset($_GET['id'])) {
    foreach ($_SESSION['members'] as $key => $m) {
        if ($m['id'] == $_GET['id']) { unset($_SESSION['members'][$key]); break; }
    }
    header("Location: ?page=admin_dashboard"); exit;
}

// Admin Action: Kufuta Hudhurio
if (isset($_GET['action']) && $_GET['action'] == 'delete_attendance' && isset($_GET['id'])) {
    foreach ($_SESSION['attendance'] as $key => $att) {
        if ($att['id'] == $_GET['id']) { unset($_SESSION['attendance'][$key]); break; }
    }
    header("Location: ?page=admin_dashboard"); exit;
}

// Admin Action: Kufuta Mchango/Zaka
if (isset($_GET['action']) && $_GET['action'] == 'delete_contribution' && isset($_GET['id'])) {
    foreach ($_SESSION['contributions'] as $key => $con) {
        if ($con['id'] == $_GET['id']) { unset($_SESSION['contributions'][$key]); break; }
    }
    header("Location: ?page=admin_dashboard"); exit;
}

// Ondoka Kwenye Mfumo (Logout)
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    unset($_SESSION['role']);
    unset($_SESSION['user']);
    header("Location: ?page=login");
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'login';
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mfumo wa Parokia ya Katoliki</title>
    <style>
        :root {
            --primary-color: #581c87;
            --primary-hover: #4c1d95;
            --accent-color: #b45309;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --success: #15803d;
            --danger: #b91c1c;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; }

        nav { background-color: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        nav h1 { font-size: 1.35rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .user-tag { color: #fef08a; font-weight: 600; font-size: 0.9rem; }
        .logout-btn { background-color: var(--danger); color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: bold; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; text-align: center; font-size: 0.95rem; }
        .success { background-color: #dcfce7; color: var(--success); border-left: 5px solid var(--success); }
        .danger { background-color: #fee2e2; color: var(--danger); border-left: 5px solid var(--danger); }

        .card { background: var(--card-bg); padding: 2rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 2rem; }
        .card-title { font-size: 1.25rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; color: #475569; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.95rem; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .btn { width: 100%; padding: 0.75rem; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .btn-success { background-color: var(--success); }
        .btn-report { background-color: #2563eb; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: bold; display: inline-block; margin-top: 1rem; }

        .auth-card { max-width: 450px; margin: 3rem auto; }
        .auth-header { text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; display: flex; flex-direction: column; gap: 8px; }
        .auth-footer a { color: var(--accent-color); font-weight: bold; text-decoration: none; }

        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        
        .table-responsive { overflow-x: auto; margin-top: 0.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem; }
        th { background-color: #f1f5f9; padding: 0.85rem; font-weight: 700; border-bottom: 2px solid var(--border); }
        td { padding: 0.85rem; border-bottom: 1px solid var(--border); }
        
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
        .badge-group { background-color: #fef3c7; color: #92400e; }
        .badge-status { background-color: #dcfce7; color: #166534; }

        .feedback-item { background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .delete-btn { background: #fee2e2; color: var(--danger); padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.8rem; text-decoration: none; font-weight: bold; }

        .announcement-box { background-color: #fffbeb; border-left: 4px solid var(--accent-color); padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem; }
        .announcement-list { list-style-type: none; }
        .announcement-list li { margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px dashed #fcd34d; }
    </style>
</head>
<body>

    <nav>
        <h1><span>⛪</span> Mfumo wa Usimamizi wa Waumini wa Parokia</h1>
        <div class="nav-actions">
            <?php if(isset($_SESSION['role'])): ?>
                <span class="user-tag">Karibu, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                <a href="?action=logout" class="logout-btn">Ondoka</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <?php echo $message; ?>

        <?php if ($page == 'login'): ?>
        <div class="card auth-card">
            <div class="auth-header">
                <span>🇻🇦</span>
                <h2 style="font-weight: 700; margin-top: 0.5rem;">Ingia Mfomoni</h2>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Barua Pepe (Email)</label>
                    <input type="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Neno la Siri (Password)</label>
                    <input type="password" name="password" required class="form-control">
                </div>
                <button type="submit" name="login" class="btn">Ingia</button>
            </form>
            <div class="auth-footer">
                <p>Huna akaunti bado? <a href="?page=register">Jisajili Hapa</a></p>
                <p><a href="?page=forgot_password" style="color: var(--danger);">Umesahau Neno la Siri?</a></p>
            </div>
        </div>

        <?php elseif ($page == 'forgot_password'): ?>
        <div class="card auth-card">
            <div class="auth-header">
                <h2 style="font-weight: 700;">Sahihisha Neno la Siri</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Weka barua pepe yako ili kuweka neno la siri jipya</p>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Barua Pepe yako iliyosajiliwa</label>
                    <input type="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Neno la Siri Jipya</label>
                    <input type="password" name="new_password" required class="form-control">
                </div>
                <button type="submit" name="reset_password" class="btn">Hifadhi Neno la Siri Jipya</button>
            </form>
            <div class="auth-footer">
                <a href="?page=login">← Rudi Kwenye Login</a>
            </div>
        </div>

        <?php elseif ($page == 'register'): ?>
        <div class="card auth-card">
            <div class="auth-header">
                <h2 style="font-weight: 700;">Sajili Akaunti Mpya</h2>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Majina Kamili</label>
                    <input type="text" name="fullname" required class="form-control" placeholder="Weka jina lako kamili">
                </div>
                <div class="form-group">
                    <label>Barua Pepe (Email)</label>
                    <input type="email" name="email" required class="form-control" placeholder="Weka email yako">
                </div>
                <div class="form-group">
                    <label>Neno la Siri (Password)</label>
                    <input type="password" name="password" required class="form-control" placeholder="Unda password ya kuingia">
                </div>
                <div class="form-group">
                    <label>Chagua Kikundi cha Kikanisa</label>
                    <select name="group" class="form-control">
                        <option value="Kwaya Kuu">Kwaya Kuu</option>
                        <option value="Viwaawa (Vijana)">Viwaawa (Vijana)</option>
                        <option value="Wanaume Wakatoliki (CMA)">Wanaume Wakatoliki (CMA)</option>
                        <option value="Wanawake Wakatoliki (WAWATA)">Wanawake Wakatoliki (WAWATA)</option>
                        <option value="Utoto Mtakatifu">Utoto Mtakatifu</option>
                    </select>
                </div>
                <button type="submit" name="register" class="btn">Kamilisha Usajili</button>
            </form>
            <div class="auth-footer">
                <a href="?page=login">← Rudi Kwenye Login</a>
            </div>
        </div>

        <?php elseif ($page == 'member_dashboard' && $_SESSION['role'] == 'member'): ?>
        <div class="dashboard-header">
            <div>
                <h2 style="font-size: 1.75rem; font-weight: 700;">Dashibodi ya Muumini</h2>
            </div>
        </div>

        <div class="announcement-box">
            <h3 style="color: var(--accent-color); font-size: 1.1rem; margin-bottom: 0.5rem;">📢 Matangazo ya Parokia</h3>
            <ul class="announcement-list">
                <?php foreach (array_reverse($_SESSION['notifications']) as $notice): ?>
                    <li><?php echo htmlspecialchars($notice); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-title">💬 Toa Maoni au Ushauri kwa Paroko</div>
                <form method="POST">
                    <div class="form-group">
                        <textarea name="feedback_text" rows="4" required placeholder="Andika hapa ushauri au maoni yako..." class="form-control" style="resize: none;"></textarea>
                    </div>
                    <button type="submit" name="send_feedback" class="btn">Tuma Maoni Sasa</button>
                </form>
            </div>
            <div class="card">
                <div class="card-title">ℹ️ Muhtasari wa Taarifa Zako</div>
                <p>Akaunti yako ipo salama. Unaweza kulogout na kuingia wakati wowote, taarifa zako zimehifadhiwa.</p>
            </div>
        </div>

        <?php elseif ($page == 'admin_dashboard' && $_SESSION['role'] == 'admin'): ?>
        <div class="dashboard-header">
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; color: #0f172a;">Panel ya Utawala (Admin)</h2>
            </div>
        </div>

        <div class="card">
            <div class="card-title">📢 Tuma Taarifa kwa Waumini Wote Waliojisajili</div>
            <form method="POST" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <input type="text" name="notice_text" required placeholder="Andika taarifa hapa kwenda kwa waumini..." class="form-control" style="flex: 1;">
                <button type="submit" name="send_notice" class="btn" style="width: auto;">Tuma Taarifa</button>
            </form>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-title">💰 Sajili Mchango wa Zaka</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Chagua Muumini</label>
                        <select name="member_name" class="form-control" required>
                            <option value="">-- Chagua Muumini aliyelipa --</option>
                            <?php foreach ($_SESSION['members'] as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['name']); ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kiasi cha Zaka (TSH)</label>
                        <input type="number" name="amount" required class="form-control" placeholder="mfano: 20000">
                    </div>
                    <button type="submit" name="add_contribution" class="btn btn-success">Hifadhi Mchango</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title">📅 Rekodi Hudhurio (Automatic Time)</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Chagua Muumini</label>
                        <select name="att_member_name" class="form-control" required>
                            <option value="">-- Chagua Muumini --</option>
                            <?php foreach ($_SESSION['members'] as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['name']); ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hali ya Hudhurio</label>
                        <select name="attendance_status" class="form-control" required>
                            <option value="Alikuwepo">Alikuwepo</option>
                            <option value="Hakuwepo">Hakuwepo</option>
                        </select>
                    </div>
                    <button type="submit" name="add_attendance" class="btn">Sajili Hudhurio Automatic</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-title">👥 Waumini Waliojisajili Kwenye Mfumo (Jumla: <?php echo count($_SESSION['members']); ?>)</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Majina Kamili</th><th>Barua Pepe</th><th>Kikundi / Chama</th><th>Hali</th><th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($_SESSION['members'])): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); font-style: italic;">Hakuna muumini aliyejisajili bado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($_SESSION['members'] as $m): ?>
                            <tr>
                                <td><?php echo $m['id']; ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($m['name']); ?></td>
                                <td><?php echo htmlspecialchars($m['email']); ?></td>
                                <td><span class="badge badge-group"><?php echo htmlspecialchars($m['group']); ?></span></td>
                                <td><span class="badge badge-status">✓ <?php echo $m['status']; ?></span></td>
                                <td>
                                    <a href="?action=delete_member&id=<?php echo $m['id']; ?>" class="delete-btn" onclick="return confirm('Futa muumini huyu?')">🗑️ Futa</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-title">📅 Ripoti ya Mahudhurio</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Siku, Tarehe na Saa</th><th>Muumini</th><th>Hali</th><th>Kitendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($_SESSION['attendance'])): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); font-style: italic;">Hakuna rekodi za mahudhurio zilizosajiliwa.</td></tr>
                            <?php else: ?>
                                <?php foreach ($_SESSION['attendance'] as $att): ?>
                                <tr>
                                    <td style="font-size: 0.85rem;"><?php echo $att['datetime']; ?></td>
                                    <td><?php echo htmlspecialchars($att['name']); ?></td>
                                    <td style="font-weight: bold; color: var(--success);"><?php echo $att['status']; ?></td>
                                    <td>
                                        <a href="?action=delete_attendance&id=<?php echo $att['id']; ?>" class="delete-btn" style="padding: 0.2rem 0.5rem;" onclick="return confirm('Futa hudhurio hili?')">X</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="?export=attendance" class="btn-report">💾 Download Ripoti ya Mahudhurio</a>
            </div>

            <div class="card">
                <div class="card-title">💰 Ripoti ya Zaka Kuu</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Muumini</th><th>Kiasi</th><th>Tarehe</th><th>Kitendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($_SESSION['contributions'])): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); font-style: italic;">Hakuna michango ya zaka iliyosajiliwa bado.</td></tr>
                                <tr style="background-color: #f8fafc; font-weight: bold;">
                                    <td colspan="2" style="text-align: right;">Jumla Kuu:</td>
                                    <td colspan="2" style="color: var(--success);">0 TSH</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $totalTithe = 0;
                                foreach ($_SESSION['contributions'] as $con): 
                                    $totalTithe += $con['amount'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($con['name']); ?></td>
                                    <td style="font-weight: 700; color: var(--success);"><?php echo number_format($con['amount']); ?> TSH</td>
                                    <td><?php echo $con['date']; ?></td>
                                    <td>
                                        <a href="?action=delete_contribution&id=<?php echo $con['id']; ?>" class="delete-btn" style="padding: 0.2rem 0.5rem;" onclick="return confirm('Je, una uhakika unataka kufuta mchango huu?')">🗑️ Futa</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background-color: #f8fafc; font-weight: bold;">
                                    <td colspan="2" style="text-align: right;">Jumla Kuu:</td>
                                    <td colspan="2" style="color: var(--success);"><?php echo number_format($totalTithe); ?> TSH</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="?export=contributions" class="btn-report" style="background-color: #166534;">💾 Download Ripoti ya Zaka</a>
            </div>
        </div>

        <div class="card">
            <div class="card-title">💬 Maoni ya Waumini</div>
            <?php if (empty($_SESSION['feedback'])): ?>
                <p style="color: var(--text-muted); font-style: italic;">Hakuna maoni mapya kutoka kwa waumini kwa sasa.</p>
            <?php else: ?>
                <?php foreach ($_SESSION['feedback'] as $fb): ?>
                <div class="feedback-item">
                    <div>
                        <h4>Kutoka: <strong><?php echo htmlspecialchars($fb['name']); ?></strong></h4>
                        <p>"<?php echo htmlspecialchars($fb['message']); ?>"</p>
                    </div>
                    <a href="?action=delete_feedback&id=<?php echo $fb['id']; ?>" class="delete-btn">🗑️ Futa</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
        console.log("Mfumo upo tayari ukiwa msafi bila mifano ya awali.");
    </script>
</body>
</html>

