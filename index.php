<?php
require_once 'api/auth_helper.php';
check_login();

if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}
// Avatar mặc định
$default_avatar = 'uploads/avatars/default-avatar.png';

$user_font_size  = $_SESSION['font_size']   ?? '16px';
$user_theme      = $_SESSION['theme_color'] ?? 'light';
$user_note_color = $_SESSION['note_color']  ?? '#ffffff';

$user_avatar = !empty($_SESSION['avatar'])
    ? $_SESSION['avatar']
    : $default_avatar;
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= htmlspecialchars($user_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteApp Pro</title>

    <script>
        (function() {
            const saved = localStorage.getItem('noteapp_theme');
            const phpTheme = "<?= htmlspecialchars($user_theme) ?>";
            document.documentElement.setAttribute('data-bs-theme', saved || phpTheme);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-size: <?= htmlspecialchars($user_font_size) ?>; 
        }
        
        :root {
            --note-default-color: <?= htmlspecialchars($user_note_color) ?>;
            --note-text-color: <?= htmlspecialchars($_SESSION['text_color'] ?? '#0A1024') ?>;
        }
        
        .note-card {
            background-color: var(--note-default-color) !important;
        }
        .note-card .card-title,
        .note-card .card-text {
            color: var(--note-text-color) !important;
        }
    </style>
</head>
<body class="bg-body text-body">

<nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">📝 NoteApp</a>
        <form class="d-flex mx-auto w-50" onsubmit="return false;">
            <input class="form-control me-2" type="search" id="searchInput" placeholder="Tìm kiếm ghi chú..." oninput="liveSearch()">
        </form>
        <div class="d-flex align-items-center gap-3">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
            <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" 
                class="nav-avatar rounded-circle" 
                onclick="showProfileModal()" 
                title="Cài đặt tài khoản"
                style="width:32px;height:32px;object-fit:cover;cursor:pointer;">
            <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['is_activated']) && $_SESSION['is_activated'] == 0): ?>
<div class="alert alert-warning alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Tài khoản chưa được xác minh!</strong>
    Vui lòng kiểm tra email và click vào link kích hoạt.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="container mt-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between bg-body-tertiary p-3 rounded mb-4 shadow-sm border">
        <div id="labelFilterBar" class="d-flex flex-wrap gap-2 align-items-center"></div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <div class="input-group input-group-sm w-auto" id="addLabelGroup">
                <input type="text" id="newLabelName" class="form-control" placeholder="Tên nhãn mới...">
                <button class="btn btn-primary" onclick="addNewLabel()">Tạo</button>
            </div>
            <button id="btnViewShared" class="btn btn-sm btn-outline-info" onclick="setViewMode('shared')">
                <i class="bi bi-people"></i> Được chia sẻ
            </button>
            <button id="btnViewTrash" class="btn btn-sm btn-outline-danger" onclick="setViewMode('trash')">
                <i class="bi bi-trash3"></i> Thùng rác
            </button>
            <button id="btnViewMyNotes" class="btn btn-sm btn-primary" onclick="setViewMode('my_notes')" style="display:none;">
                <i class="bi bi-house"></i> Ghi chú của tôi
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button id="btnCreateNote" class="btn btn-primary shadow-sm px-4 fw-bold" onclick="openNoteModal()">
            <i class="bi bi-plus-lg"></i> Tạo ghi chú mới
        </button>
        <button id="toggleBulkModeBtn" class="btn btn-outline-secondary shadow-sm px-3" title="Chọn nhiều ghi chú">
            <i class="bi bi-check2-square"></i> Chọn
        </button>
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;"></h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
    <!-- Bulk action toolbar (ẩn mặc định) -->
    <div id="bulkToolbar" class="fixed-bottom mb-3">
    <div class="bg-body-tertiary rounded-pill shadow-lg p-2 d-flex gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger rounded-pill"><i class="bi bi-trash3"></i> Xóa</button>
        <button id="bulkRestoreBtn" class="btn btn-sm btn-success rounded-pill d-none"><i class="bi bi-arrow-counterclockwise"></i> Khôi phục</button>
        <button id="bulkPermanentBtn" class="btn btn-sm btn-dark rounded-pill d-none"><i class="bi bi-x-octagon"></i> Xóa vĩnh viễn</button>
        <button id="bulkShareBtn" class="btn btn-sm btn-info rounded-pill"><i class="bi bi-share"></i> Chia sẻ</button>
        <button id="bulkCancelBtn" class="btn btn-sm btn-secondary rounded-pill"><i class="bi bi-x-lg"></i> Hủy</button>
        <span id="bulkCounter" class="align-self-center ms-2 small text-muted">0</span>
    </div>
</div>
</div>

<!-- ==================== MODALS ==================== -->
<?php include 'modals.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- APP CONFIG -->
<script>
    window.APP_CONFIG = {
    userId:      <?= (int)($_SESSION['user_id'] ?? 0) ?>,
    userName:    "<?= addslashes($_SESSION['display_name'] ?? 'User') ?>",
    email:       "<?= addslashes($_SESSION['email'] ?? '') ?>",
    displayName: "<?= addslashes($_SESSION['display_name'] ?? '') ?>",
    avatar:      "<?= htmlspecialchars($user_avatar) ?>",
    fontSize:    "<?= htmlspecialchars($user_font_size) ?>",
    theme:       "<?= htmlspecialchars($user_theme) ?>",
    noteColor:   "<?= htmlspecialchars($user_note_color) ?>",
    textColor:   "<?= htmlspecialchars($_SESSION['text_color'] ?? '#0A1024') ?>",
    fontFamily:  "<?= htmlspecialchars($_SESSION['font_family'] ?? 'Inter, system-ui, sans-serif') ?>",
    csrf_token:  "<?= $_SESSION['csrf_token'] ?? '' ?>"
};
</script>

<!-- Main JS -->
<script src="assets/js/app.js"></script>

<!-- Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(() => console.log('SW registered'))
            .catch(err => console.log('SW failed:', err));
    });
}
</script>

<!-- Floating Button -->
<button class="floating-create btn btn-primary btn-lg rounded-circle shadow" 
        onclick="openNoteModal()" 
        style="position:fixed; bottom:25px; right:25px; width:65px; height:65px; z-index:1050;">
    <i class="bi bi-plus-lg fs-3"></i>
</button>

</body>
</html>