<?php
require_once 'api/auth_helper.php';

check_login();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
 <style>
        body{
            font-size: <?= htmlspecialchars($user_font_size) ?> !important;
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
        <div class="d-flex align-items-center gap-3 text-white">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
            <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" class="nav-avatar"
                 onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()" title="Cài đặt tài khoản">
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
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;">...</h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
</div>

<!-- Modal ghi chú -->
<div class="modal fade" id="noteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg" id="modalContentWrapper">
            <div class="modal-header border-0 pb-0">
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent" placeholder="Tiêu đề..." oninput="autoSave()">
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10" placeholder="Bạn đang nghĩ gì?..." oninput="autoSave()"></textarea>
                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mb-3"></div>

                <div id="colorSection" class="mb-3" style="display:none;">
                    <span class="small text-muted me-2"><i class="bi bi-palette"></i> Màu:</span>
                    <span class="color-btn" style="background:#ffffff" onclick="changeColor('')"></span>
                    <span class="color-btn" style="background:#f28b82" onclick="changeColor('#f28b82')"></span>
                    <span class="color-btn" style="background:#fbbc04" onclick="changeColor('#fbbc04')"></span>
                    <span class="color-btn" style="background:#fff475" onclick="changeColor('#fff475')"></span>
                    <span class="color-btn" style="background:#ccff90" onclick="changeColor('#ccff90')"></span>
                    <span class="color-btn" style="background:#a7ffeb" onclick="changeColor('#a7ffeb')"></span>
                    <span class="color-btn" style="background:#cbf0f8" onclick="changeColor('#cbf0f8')"></span>
                    <span class="color-btn" style="background:#d7aefb" onclick="changeColor('#d7aefb')"></span>
                </div>

                <div class="p-3 bg-body-secondary rounded border mb-3" id="shareManagerSection" style="display:none;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus"></i> Chia sẻ ghi chú này</h6>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="share_input" class="form-control" placeholder="Nhập email (cách nhau bởi dấu phẩy)...">
                        <select id="sharePermission" class="form-select" style="max-width: 140px;">
                            <option value="read">Chỉ xem</option>
                            <option value="edit">Cho phép sửa</option>
                        </select>
                        <button class="btn btn-success" onclick="shareNote()">Chia sẻ</button>
                    </div>
                    <small class="text-muted">Ví dụ: user1@gmail.com, user2@gmail.com</small>
                    <ul id="sharedUsersList" class="list-group list-group-flush small mt-3"></ul>
                </div>

                <div class="p-3 bg-body-tertiary rounded border" id="toolsSection" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-image"></i> Ảnh
                                <input type="file" id="imageInput" hidden accept="image/*" onchange="uploadImage()">
                            </label>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-warning btn-sm w-100" id="btnLock" onclick="toggleLock()">
                                <i class="bi bi-lock"></i> Khóa
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select id="labelSelector" class="form-select form-select-sm" onchange="addLabelToNote()">
                                <option value="">+ Nhãn</option>
                            </select>
                        </div>
                    </div>
                    <div id="noteLabelsContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-danger btn-sm" id="btnTrashNote" onclick="deleteNote('trash')" style="display:none;">
                        <i class="bi bi-trash"></i> Xóa (Vào thùng rác)
                    </button>
                    <button class="btn btn-success btn-sm" id="btnRestoreNote" onclick="restoreNote()" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                    </button>
                    <button class="btn btn-danger btn-sm ms-2" id="btnDeletePermanent" onclick="deleteNote('permanent')" style="display:none;">
                        <i class="bi bi-x-octagon"></i> Xóa vĩnh viễn
                    </button>
                </div>
                <span id="saveStatus" class="text-muted small fst-italic"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Profile -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>" class="rounded-circle mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                <div class="mb-4">
                    <label class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-camera"></i> Đổi ảnh đại diện
                        <input type="file" id="inputAvatar" hidden accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <hr>
                <div class="row text-start g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kích thước chữ</label>
                        <select id="settingFontSize" class="form-select">
                            <option value="14px" <?= $user_font_size=='14px'?'selected':'' ?>>Nhỏ</option>
                            <option value="16px" <?= $user_font_size=='16px'?'selected':'' ?>>Vừa</option>
                            <option value="18px" <?= $user_font_size=='18px'?'selected':'' ?>>Lớn</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Giao diện</label>
                        <select id="settingTheme" class="form-select">
                            <option value="light" <?= $user_theme=='light'?'selected':'' ?>>Sáng</option>
                            <option value="dark"  <?= $user_theme=='dark'?'selected':'' ?>>Tối</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Màu ghi chú</label>
                        <input type="color" id="settingNoteColor" class="form-control form-control-color w-100" value="<?= htmlspecialchars($user_note_color) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success px-4" onclick="saveProfile()">
                    <i class="bi bi-check2"></i> Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nhập Mật Khẩu -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle">🔒 Nhập mật khẩu ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="password" id="notePasswordInput" class="form-control" placeholder="Nhập mật khẩu..." autocomplete="current-password">
                <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="submitNotePassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ====================== BIẾN TOÀN CỤC ======================
const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
let typingTimer, searchTimer;
let currentLabelId = null;
let currentViewMode = 'my_notes';
let currentPermission = 'owner';
let isLockedState = false;
let currentNoteId = null;
let passwordModalInstance = null;
let tempOpenData = null;
let autoRefreshInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    setViewMode('my_notes');
});

// ====================== VIEW MODE & SEARCH ======================
function setViewMode(mode) {

    const container =
        document.getElementById('notesContainer');

    /* fade out */

    container.style.transition =
        'all .25s ease';

    container.style.opacity = '0';

    container.style.transform =
        'translateY(10px) scale(.98)';

    setTimeout(() => {

        currentViewMode = mode;
        currentLabelId = null;

        document.getElementById('btnViewShared').style.display =
            mode === 'shared' ? 'none' : 'block';

        document.getElementById('btnViewTrash').style.display =
            mode === 'trash' ? 'none' : 'block';

        document.getElementById('btnViewMyNotes').style.display =
            mode === 'my_notes' ? 'none' : 'block';

        const viewTitle =
            document.getElementById('viewTitle');

        const btnCreate =
            document.getElementById('btnCreateNote');

        const addLabelGroup =
            document.getElementById('addLabelGroup');

        if (mode === 'my_notes') {

            viewTitle.style.display = 'none';

            btnCreate.style.display = 'block';

            addLabelGroup.style.display = 'flex';

        } 
        else if (mode === 'trash') {

            viewTitle.innerHTML =
                '🗑️ THÙNG RÁC';

            viewTitle.style.display = 'block';

            viewTitle.className =
                'text-danger fw-bold m-0 align-self-center';

            btnCreate.style.display = 'none';

            addLabelGroup.style.display = 'none';

        } 
        else if (mode === 'shared') {

            viewTitle.innerHTML =
                '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';

            viewTitle.style.display = 'block';

            viewTitle.className =
                'text-info fw-bold m-0 align-self-center';

            btnCreate.style.display = 'none';

            addLabelGroup.style.display = 'none';
        }

        loadFilterLabels(() => {

            liveSearch();

            /* fade in */

            setTimeout(() => {

                container.style.opacity = '1';

                container.style.transform =
                    'translateY(0) scale(1)';

            }, 100);
        });

        startAutoRefresh();

    }, 180);
}

function liveSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        let url = `api/search.php?q=${encodeURIComponent(document.getElementById('searchInput').value)}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;
        fetch(url).then(res => res.json()).then(renderNotes).catch(() => renderNotes([]));
    }, 300);
}

// ====================== RENDER NOTES ======================
function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = { trash: 'Thùng rác trống.', shared: 'Chưa có ghi chú nào được chia sẻ.', my_notes: 'Chưa có ghi chú nào.' };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName = n.owner_name || '';
        const permission = n.permission || 'owner';

        let icons = '';
        if (n.is_locked == 1) icons += '<i class="bi bi-lock-fill text-warning me-1" title="Đã khóa"></i>';
        if (ownerName) icons += '<i class="bi bi-people-fill text-info me-1" title="Được chia sẻ"></i>';
        if (n.is_pinned == 1) icons += '<i class="bi bi-pin-fill text-danger me-1" title="Đã ghim"></i>';

        let shareInfo = ownerName ? `
            <div class="position-absolute bottom-0 start-0 end-0 px-3 pb-2 d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(ownerName)}</small>
                ${permission === 'edit' ? `<span class="badge bg-success ms-1">✏️ Edit</span>` : `<span class="badge bg-secondary ms-1">👁️ View</span>`}
            </div>` : '';

        const card = document.createElement('div');
        card.className = 'card note-card';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className = 'card-body position-relative pb-4';
        body.dataset.id = n.id;
        body.dataset.title = n.title || '';
        body.dataset.content = n.content || '';
        body.dataset.isLocked = n.is_locked || 0;
        body.dataset.color = n.color || '';
        body.dataset.permission = permission;
        body.dataset.ownerName = ownerName;

        body.addEventListener('click', () => handleNoteOpen(parseInt(body.dataset.id), body.dataset.title, body.dataset.content, parseInt(body.dataset.isLocked), body.dataset.color, body.dataset.permission, body.dataset.ownerName));

        body.innerHTML = `
            ${currentViewMode === 'my_notes' ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0" onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})"><i class="bi ${pinClass} fs-5"></i></button>` : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1">${icons} ${escapeHtml(n.title) || 'Không tiêu đề'}</h5>
            <p class="card-text text-muted text-truncate" style="white-space:pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">${escapeHtml(n.content) || 'Không có nội dung...'}</p>
            ${shareInfo}
        `;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    currentNoteId = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        document.getElementById('passwordModalTitle').textContent = '🔒 Ghi chú đã bị khóa';
        document.getElementById('notePasswordInput').value = '';
        document.getElementById('passwordError').style.display = 'none';
        passwordModalInstance.show();
        setTimeout(() => document.getElementById('notePasswordInput').focus(), 500);
        window.tempOpenData = {id, title, content, color, permission, ownerName};
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    const password = document.getElementById('notePasswordInput').value.trim();
    const errorEl = document.getElementById('passwordError');
    if (!password) {
        errorEl.textContent = "Vui lòng nhập mật khẩu!";
        errorEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append('note_id', currentNoteId);
    fd.append('password', password);

    fetch('api/verify_note.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            passwordModalInstance.hide();
            isLockedState = true;
            openNoteModal(window.tempOpenData.id, d.title, d.content, d.color, d.permission, window.tempOpenData.ownerName);
        } else {
            errorEl.textContent = d.message || "Mật khẩu không đúng!";
            errorEl.style.display = 'block';
            document.getElementById('notePasswordInput').value = '';
            document.getElementById('notePasswordInput').focus();
        }
    });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    document.getElementById('noteId').value = id;
    document.getElementById('noteTitle').value = title;
    document.getElementById('noteContent').value = content;
    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML = '';
    document.getElementById('saveStatus').innerText = '';
    document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';

    const isTrash = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice = document.getElementById('sharedNotice');

    ['toolsSection','colorSection','shareManagerSection','btnTrashNote','btnRestoreNote','btnDeletePermanent'].forEach(el => {
        document.getElementById(el).style.display = 'none';
    });

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
    } else if (isShared) {
        notice.style.display = 'block';
        notice.innerHTML = `
            <strong>Được chia sẻ bởi:</strong> <b>${escapeHtml(ownerName)}</b><br>
            <strong>Quyền:</strong> <b>${permission === 'edit' ? '✅ Có thể chỉnh sửa' : '👁️ Chỉ xem'}</b>
        `;
        document.getElementById('noteTitle').readOnly = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';
        if (id) fetch(`api/get_note_images.php?note_id=${id}`).then(r => r.json()).then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly = false;
        document.getElementById('noteContent').readOnly = false;
        if (id) {
            document.getElementById('toolsSection').style.display = 'block';
            document.getElementById('colorSection').style.display = 'block';
            document.getElementById('shareManagerSection').style.display = 'block';
            document.getElementById('btnTrashNote').style.display = 'block';
            document.getElementById('btnLock').innerHTML = isLockedState ? '<i class="bi bi-unlock"></i> Mở khóa' : '<i class="bi bi-lock"></i> Đặt mật khẩu';
            fetch(`api/get_note_images.php?note_id=${id}`).then(r => r.json()).then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
        }
    }

    if (isShared && permission === 'edit') startAutoRefresh();
    noteModal.show();
}

// ====================== AUTO REFRESH ======================
function startAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    if (currentViewMode === 'shared' && currentPermission === 'edit') {
        autoRefreshInterval = setInterval(liveSearch, 4000);
    }
}

function closeAndReload() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    document.getElementById('saveStatus').innerText = 'Đang lưu...';
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        const id = document.getElementById('noteId').value;
        const t = document.getElementById('noteTitle').value;
        const c = document.getElementById('noteContent').value;
        if (!t.trim() && !c.trim()) return;
        fetch('api/save_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(t)}&content=${encodeURIComponent(c)}`
        }).then(res => res.json()).then(d => {
            if (d.success && !id) {
                document.getElementById('noteId').value = d.note_id;
                document.getElementById('toolsSection').style.display = 'block';
                document.getElementById('colorSection').style.display = 'block';
                document.getElementById('shareManagerSection').style.display = 'block';
                document.getElementById('btnTrashNote').style.display = 'block';
                refreshLabelSelector();
            }
            document.getElementById('saveStatus').innerText = d.success ? 'Đã lưu' : 'Lỗi lưu!';
            if (d.success) liveSearch();
        });
    }, 800);
}

// ====================== CHIA SẺ ======================
function shareNote() {
    const noteId = document.getElementById('noteId').value;
    const input = document.getElementById('share_input').value.trim();
    const perm = document.getElementById('sharePermission').value;
    if (!noteId) return alert('Vui lòng lưu ghi chú trước!');
    if (!input) return alert('Vui lòng nhập email!');

    const emails = input.split(',').map(e => e.trim()).filter(Boolean);
    const fd = new FormData();
    fd.append('note_id', noteId);
    fd.append('permission', perm);
    fd.append('share_with', emails.join(','));

    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
                liveSearch();
            }
        });
}

function loadSharedUsers(noteId) {
    fetch(`api/get_shares.php?note_id=${noteId}`).then(r => r.json()).then(users => {
        const list = document.getElementById('sharedUsersList');
        list.innerHTML = '';
        users.forEach(u => {
            list.innerHTML += `<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                <span><i class="bi bi-person-check text-success"></i> ${escapeHtml(u.display_name)} <small>(${u.permission})</small></span>
                <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
            </li>`;
        });
    });
}

function revokeShare(shareId) {
    if (!confirm('Thu hồi quyền?')) return;
    const fd = new FormData();
    fd.append('share_id', shareId);
    fetch('api/revoke_share.php', { method: 'POST', body: fd }).then(() => loadSharedUsers(document.getElementById('noteId').value));
}

// ====================== KHÓA ======================
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    if (isLockedState) {
        if (confirm('Gỡ khóa?')) unlockNote(id);
        else changeNotePassword(id);
    } else {
        const p = prompt('Mật khẩu mới (≥4 ký tự):');
        if (p && p.length >= 4) lockNote(id, p);
    }
}

function lockNote(id, password) {
    const fd = new FormData();
    fd.append('note_id', id); fd.append('password', password); fd.append('action', 'lock');
    fetch('api/lock_note.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){ isLockedState=true; alert('Đã khóa!'); liveSearch(); closeAndReload(); }
    });
}

function unlockNote(id) {
    const fd = new FormData();
    fd.append('note_id', id); fd.append('action', 'unlock');
    fetch('api/lock_note.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){ isLockedState=false; alert('Đã gỡ khóa!'); liveSearch(); closeAndReload(); }
    });
}

function changeNotePassword(id) {
    const p = prompt('Mật khẩu mới:');
    if (p && p.length >= 4) lockNote(id, p);
}

// ====================== ẢNH ======================
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f = document.getElementById('imageInput').files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    fetch('api/upload_image.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d => {
            if(d.success) renderImage(d.file_path, d.image_id, 'owner');
            else alert(d.message);
            document.getElementById('imageInput').value = '';
        });
}

function renderImage(path, id, perm) {
    const del = perm === 'owner' ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle" style="width:24px;height:24px;margin:-8px -8px 0 0;" onclick="deleteImage(${id},this)"><i class="bi bi-x"></i></button>` : '';
    document.getElementById('imagePreviewContainer').innerHTML += `<div class="position-relative shadow-sm rounded"><img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">${del}</div>`;
}

function deleteImage(id, btn) {
    if(confirm('Xóa ảnh?')) {
        fetch('api/delete_image.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}`})
            .then(r=>r.json()).then(d=>{ if(d.success) btn.parentElement.remove(); });
    }
}

// ====================== LABEL ======================
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list').then(r=>r.json()).then(ls => {
        const bar = document.getElementById('labelFilterBar');
        bar.innerHTML = `<button class="btn btn-sm ${currentLabelId===null?'btn-dark':'btn-outline-secondary'}" onclick="filterLabel(null)">Tất cả</button>`;
        ls.forEach(l => {
            bar.innerHTML += `<div class="btn-group btn-group-sm">
                <button class="btn ${currentLabelId==l.id?'btn-dark':'btn-outline-secondary'}" onclick="filterLabel(${l.id})">${escapeHtml(l.name)}</button>
                <button class="btn btn-outline-secondary" onclick="event.stopPropagation();renameLabel(${l.id},'${escapeHtml(l.name).replace(/'/g,"\\'")}')"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger" onclick="event.stopPropagation();deleteLabel(${l.id})"><i class="bi bi-x"></i></button>
            </div>`;
        });
        if(callback) callback();
    });
}

function filterLabel(id) {
    currentLabelId = id;
    loadFilterLabels(() => liveSearch());
}

function deleteLabel(id) {
    if(confirm('Xóa nhãn?')) {
        fetch('api/manage_labels.php?action=delete', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}`})
            .then(() => { if(currentLabelId==id) currentLabelId=null; loadFilterLabels(() => liveSearch()); });
    }
}

function addNewLabel() {
    const name = document.getElementById('newLabelName').value.trim();
    if(!name) return;
    fetch('api/manage_labels.php?action=add', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`name=${encodeURIComponent(name)}`})
        .then(() => { document.getElementById('newLabelName').value=''; loadFilterLabels(); });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list').then(r=>r.json()).then(ls => {
        const s = document.getElementById('labelSelector');
        s.innerHTML = '<option value="">+ Nhãn</option>';
        ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
    });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`).then(r=>r.json()).then(ls => {
        const c = document.getElementById('noteLabelsContainer');
        c.innerHTML = '';
        ls.forEach(l => c.innerHTML += `<span class="badge bg-secondary">${escapeHtml(l.name)} <i class="bi bi-x-circle-fill cp" onclick="removeLabel(${nid},${l.id})"></i></span>`);
    });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if(!lid) return;
    fetch('api/set_note_label.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`note_id=${nid}&label_id=${lid}&action=add`})
        .then(() => { loadLabelsForNote(nid); liveSearch(); document.getElementById('labelSelector').value=''; });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`note_id=${nid}&label_id=${lid}&action=remove`})
        .then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== TIỆN ÍCH ======================
function setView(v) {
    document.getElementById('notesContainer').className = v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function escapeHtml(s) {
    if (!s) return '';
    return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatar').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile() {
    const fd = new FormData();
    if (document.getElementById('inputAvatar').files[0]) fd.append('avatar', document.getElementById('inputAvatar').files[0]);
    fd.append('font_size', document.getElementById('settingFontSize').value);
    fd.append('theme_color', document.getElementById('settingTheme').value);
    fd.append('note_color', document.getElementById('settingNoteColor').value);

    fetch('api/update_profile.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            if(data.success) { alert('Cập nhật thành công!'); location.reload(); }
            else alert(data.message || 'Lỗi');
        });
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}&is_pinned=${state}`})
        .then(() => liveSearch());
}

function deleteNote(action) {
    const id = document.getElementById('noteId').value;
    if (confirm(action === 'trash' ? 'Chuyển vào thùng rác?' : 'Xóa vĩnh viễn?')) {
        fetch('api/delete_note.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}&action=${action}`})
            .then(() => closeAndReload());
    }
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    fetch('api/restore_note.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}`})
        .then(() => { alert('Khôi phục thành công!'); closeAndReload(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&name=${encodeURIComponent(newName.trim())}`
    }).then(() => loadFilterLabels(() => liveSearch()));
}
// ====================== WEBSOCKET REALTIME ======================
let ws = null;
let currentNoteIdForWS = null;

function connectWebSocket() {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;

    ws = new WebSocket('ws://localhost:8080');

    ws.onopen = () => {
        console.log('✅ WebSocket Connected Successfully');
    };

    ws.onmessage = (event) => {
        console.log('📥 Received:', event.data);
        try {
            const data = JSON.parse(event.data);
            if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                const titleEl = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');

                if (document.activeElement !== titleEl) titleEl.value = data.title || titleEl.value;
                if (document.activeElement !== contentEl) contentEl.value = data.content || contentEl.value;
            }
        } catch(e) {
            console.error('Parse error:', e);
        }
    };

    ws.onclose = () => {
        console.log('WebSocket closed. Reconnecting...');
        setTimeout(connectWebSocket, 2000);
    };

    ws.onerror = (err) => {
        console.error('WebSocket Error:', err);
    };
}

function startRealtimeForNote(noteId) {
    currentNoteIdForWS = noteId;
    connectWebSocket();
}

function sendNoteUpdate() {
    if (!ws || ws.readyState !== WebSocket.OPEN || !currentNoteIdForWS) return;

    const title = document.getElementById('noteTitle').value;
    const content = document.getElementById('noteContent').value;

    ws.send(JSON.stringify({
        note_id: currentNoteIdForWS,
        title: title,
        content: content,
        user_name: "<?= addslashes($_SESSION['display_name'] ?? 'User') ?>"
    }));
}

// Tích hợp vào autoSave
const originalAutoSave = autoSave || function(){};
autoSave = function() {
    originalAutoSave();
    sendNoteUpdate();
};

// Dừng khi đóng modal
const originalClose = closeAndReload;
closeAndReload = function() {
    currentNoteIdForWS = null;
    if (ws) ws.close();
    originalClose();
};
// ====================== THAY ĐỔI MÀU GHI CHÚ ======================
function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) {
        alert("Vui lòng lưu ghi chú trước khi đổi màu!");
        return;
    }

    fetch('api/change_color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&color=${encodeURIComponent(color)}`
    })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';
            liveSearch(); // Cập nhật lại danh sách
        } else {
            alert('Không thể đổi màu ghi chú!');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi kết nối khi đổi màu!');
    });
}
</script>
<button class="floating-create"
    onclick="openNoteModal()">

    <i class="bi bi-plus-lg"></i>

</button>
</body>
</html>