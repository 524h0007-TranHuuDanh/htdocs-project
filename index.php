<?php
require_once 'api/auth_helper.php';
check_login();

$user_font_size = $_SESSION['font_size']    ?? '16px';
$user_theme     = $_SESSION['theme_color']  ?? 'light';
$user_avatar    = $_SESSION['avatar']       ?? 'default-avatar.png';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= htmlspecialchars($user_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteApp Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { font-size: <?= htmlspecialchars($user_font_size) ?> !important; transition: background-color 0.3s, color 0.3s; }
        .note-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid rgba(0,0,0,0.125); }
        .note-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .note-grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .note-list-view { display: flex; flex-direction: column; gap: 15px; }
        .cp { cursor: pointer; }
        textarea { resize: none; }
        .color-btn { width: 25px; height: 25px; border-radius: 50%; border: 1px solid #ccc; display: inline-block; cursor: pointer; margin-right: 5px; }
        .color-btn:hover { transform: scale(1.1); }
        .nav-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid white; cursor: pointer; transition: transform 0.2s; }
        .nav-avatar:hover { transform: scale(1.1); }
    </style>
</head>
<body class="bg-body text-body">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">📝 NoteApp</a>
        <form class="d-flex mx-auto w-50" onsubmit="return false;">
            <input class="form-control me-2" type="search" id="searchInput" placeholder="Tìm kiếm ghi chú..." oninput="liveSearch()">
        </form>
        <div class="d-flex align-items-center gap-3 text-white">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
            <img src="<?= htmlspecialchars($user_avatar) ?>" class="nav-avatar shadow-sm"
                 onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()"
                 title="Cài đặt tài khoản">
            <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
        </div>
    </div>
</nav>

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
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent"
                       placeholder="Tiêu đề..." oninput="autoSave()">
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10"
                          placeholder="Bạn đang nghĩ gì?..." oninput="autoSave()"></textarea>
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
                        <input type="text" id="share_input" class="form-control"
                               placeholder="Nhập Email hoặc Tên người nhận...">
                        <select id="sharePermission" class="form-select" style="max-width: 130px;">
                            <option value="read">Chỉ xem</option>
                            <option value="edit">Cho phép sửa</option>
                        </select>
                        <button class="btn btn-success" onclick="shareNote()">Gửi</button>
                    </div>
                    <ul id="sharedUsersList" class="list-group list-group-flush small"></ul>
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

<!-- Modal profile -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>"
                     class="rounded-circle mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                <div class="mb-4">
                    <label class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-camera"></i> Đổi ảnh đại diện
                        <input type="file" id="inputAvatar" hidden accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <hr>
                <div class="row text-start g-3 mt-2">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">Kích thước chữ</label>
                        <select id="settingFontSize" class="form-select">
                            <option value="14px" <?= $user_font_size=='14px'?'selected':'' ?>>Nhỏ</option>
                            <option value="16px" <?= $user_font_size=='16px'?'selected':'' ?>>Vừa (Mặc định)</option>
                            <option value="18px" <?= $user_font_size=='18px'?'selected':'' ?>>Lớn</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted">Giao diện (Theme)</label>
                        <select id="settingTheme" class="form-select">
                            <option value="light" <?= $user_theme=='light'?'selected':'' ?>>Sáng</option>
                            <option value="dark"  <?= $user_theme=='dark' ?'selected':'' ?>>Tối</option>
                        </select>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
let typingTimer, searchTimer;
let currentLabelId  = null;
let isLockedState   = false;
let currentViewMode = 'my_notes';
let currentPermission = 'owner';

document.addEventListener('DOMContentLoaded', () => {
    setViewMode('my_notes');
});

// ── VIEW MODE ─────────────────────────────────────────────────
function setViewMode(mode) {
    currentViewMode = mode;
    currentLabelId  = null;

    document.getElementById('btnViewShared').style.display  = mode === 'shared'   ? 'none' : 'block';
    document.getElementById('btnViewTrash').style.display   = mode === 'trash'    ? 'none' : 'block';
    document.getElementById('btnViewMyNotes').style.display = mode === 'my_notes' ? 'none' : 'block';

    const viewTitle    = document.getElementById('viewTitle');
    const btnCreate    = document.getElementById('btnCreateNote');
    const addLabelGroup = document.getElementById('addLabelGroup');

    if (mode === 'my_notes') {
        viewTitle.style.display = 'none';
        btnCreate.style.display = 'block';
        addLabelGroup.style.display = 'flex';
    } else if (mode === 'trash') {
        viewTitle.innerHTML  = '🗑️ THÙNG RÁC';
        viewTitle.style.display = 'block';
        viewTitle.className  = 'text-danger fw-bold m-0 align-self-center';
        btnCreate.style.display = 'none';
        addLabelGroup.style.display = 'none';
    } else if (mode === 'shared') {
        viewTitle.innerHTML  = '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';
        viewTitle.style.display = 'block';
        viewTitle.className  = 'text-info fw-bold m-0 align-self-center';
        btnCreate.style.display = 'none';
        addLabelGroup.style.display = 'none';
    }

    // SỬA WARN: Gọi loadFilterLabels với callback để tránh race condition
    loadFilterLabels(() => liveSearch());
}

// ── SEARCH ────────────────────────────────────────────────────
function liveSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        let url = `api/search.php?q=${encodeURIComponent(document.getElementById('searchInput').value)}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;
        fetch(url).then(res => res.json()).then(renderNotes).catch(() => renderNotes([]));
    }, 300);
}

function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = {
            trash: 'Thùng rác trống.',
            shared: 'Chưa có ghi chú nào được chia sẻ với bạn.',
            my_notes: 'Chưa có ghi chú nào. Hãy tạo mới!'
        };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass   = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor    = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName  = n.owner_name  || '';
        const permission = n.permission  || 'owner';
        const shareBadge = ownerName
            ? `<span class="badge bg-info text-dark position-absolute bottom-0 start-0 m-2"><i class="bi bi-person"></i> Từ: ${escapeHtml(ownerName)}</span>`
            : '';

        // SỬA: Dùng data-attribute thay vì inline onclick với string escaping
        const card = document.createElement('div');
        card.className = 'card note-card h-100';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className = 'card-body position-relative pb-4';
        body.dataset.id         = n.id;
        body.dataset.title      = n.title    || '';
        body.dataset.content    = n.content  || '';
        body.dataset.isLocked   = n.is_locked;
        body.dataset.color      = n.color    || '';
        body.dataset.permission = permission;
        body.dataset.ownerName  = ownerName;
        body.addEventListener('click', function () {
            handleNoteOpen(
                parseInt(this.dataset.id),
                this.dataset.title,
                this.dataset.content,
                parseInt(this.dataset.isLocked),
                this.dataset.color,
                this.dataset.permission,
                this.dataset.ownerName
            );
        });

        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                       onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                       <i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate pe-4 ${n.is_locked ? 'text-warning' : ''}">${escapeHtml(n.title) || 'Không tiêu đề'}</h5>
            <p class="card-text text-muted text-truncate" style="white-space:pre-wrap;">${escapeHtml(n.content) || '...'}</p>
            ${shareBadge}`;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ── MỞ GHI CHÚ ───────────────────────────────────────────────
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    if (isLocked && currentViewMode !== 'trash') {
        const pwd = prompt('Nhập mật khẩu để xem:');
        if (!pwd) return;
        const fd = new FormData();
        fd.append('note_id', id);
        fd.append('password', pwd);
        fetch('api/verify_note.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(d => {
                if (d.success) {
                    isLockedState = true;
                    openNoteModal(id, d.title, d.content, d.color, d.permission, ownerName);
                } else {
                    alert(d.message);
                }
            });
    } else {
        isLockedState = false;
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    document.getElementById('noteId').value            = id;
    document.getElementById('noteTitle').value         = title;
    document.getElementById('noteContent').value       = content;
    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText    = '';
    document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';

    const isTrash  = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice   = document.getElementById('sharedNotice');

    // Reset tất cả sections
    ['toolsSection', 'colorSection', 'shareManagerSection',
     'btnTrashNote', 'btnRestoreNote', 'btnDeletePermanent'].forEach(el => {
        document.getElementById(el).style.display = 'none';
    });

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display    = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
    } else if (isShared) {
        notice.style.display = 'block';
        notice.innerHTML = `Được chia sẻ bởi <b>${escapeHtml(ownerName)}</b> | Quyền của bạn: <b>${permission === 'read' ? 'Chỉ xem' : 'Có thể sửa'}</b>`;
        document.getElementById('noteTitle').readOnly   = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';
        if (id) {
            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(res => res.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
        }
    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = false;
        document.getElementById('noteContent').readOnly = false;
        if (id) {
            document.getElementById('toolsSection').style.display     = 'block';
            document.getElementById('colorSection').style.display     = 'block';
            document.getElementById('shareManagerSection').style.display = 'block';
            document.getElementById('btnTrashNote').style.display     = 'block';
            document.getElementById('btnLock').innerHTML = isLockedState
                ? '<i class="bi bi-unlock"></i> Mở khóa'
                : '<i class="bi bi-lock"></i> Đặt mật khẩu';
            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(res => res.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
        }
    }
    noteModal.show();
}

// ── AUTO SAVE ─────────────────────────────────────────────────
function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    document.getElementById('saveStatus').innerText = 'Đang lưu...';
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        const id = document.getElementById('noteId').value;
        const t  = document.getElementById('noteTitle').value;
        const c  = document.getElementById('noteContent').value;
        if (!t.trim() && !c.trim()) return;
        fetch('api/save_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(t)}&content=${encodeURIComponent(c)}`
        })
        .then(res => res.json())
        .then(d => {
            if (d.success && !id) {
                document.getElementById('noteId').value = d.note_id;
                document.getElementById('toolsSection').style.display      = 'block';
                document.getElementById('colorSection').style.display      = 'block';
                document.getElementById('shareManagerSection').style.display = 'block';
                document.getElementById('btnTrashNote').style.display      = 'block';
                refreshLabelSelector();
            }
            document.getElementById('saveStatus').innerText = d.success ? 'Đã lưu' : 'Lỗi lưu!';
            if (d.success) liveSearch();
        });
    }, 800);
}

// ── CHIA SẺ ───────────────────────────────────────────────────
function shareNote() {
    const noteId    = document.getElementById('noteId').value;
    const shareWith = document.getElementById('share_input').value.trim();
    const perm      = document.getElementById('sharePermission').value;
    if (!noteId)    return alert('Vui lòng ghi nội dung ghi chú và chờ lưu tự động trước khi chia sẻ!');
    if (!shareWith) return alert('Vui lòng nhập Email hoặc Tên người nhận!');
    const fd = new FormData();
    fd.append('note_id',    noteId);
    fd.append('share_with', shareWith);
    fd.append('permission', perm);
    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            alert(d.message);
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
            }
        })
        .catch(() => alert('Lỗi kết nối tới server.'));
}

function loadSharedUsers(noteId) {
    fetch(`api/get_shares.php?note_id=${noteId}`)
        .then(r => r.json())
        .then(users => {
            const list = document.getElementById('sharedUsersList');
            list.innerHTML = '';
            users.forEach(u => {
                list.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                        <span><i class="bi bi-person-check text-success"></i>
                        ${escapeHtml(u.display_name)}
                        <small class="text-muted fst-italic">(${escapeHtml(u.permission)})</small></span>
                        <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
                    </li>`;
            });
        });
}

function revokeShare(shareId) {
    if (!confirm('Bạn có chắc muốn thu hồi quyền truy cập của người này?')) return;
    const fd = new FormData();
    fd.append('share_id', shareId);
    fetch('api/revoke_share.php', { method: 'POST', body: fd })
        .then(() => loadSharedUsers(document.getElementById('noteId').value));
}

// ── MÀU / XÓA / KHÔI PHỤC / GHIM ─────────────────────────────
function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    fetch('api/change_color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&color=${encodeURIComponent(color)}`
    }).then(() => {
        document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';
        liveSearch();
    });
}

function deleteNote(action) {
    const id  = document.getElementById('noteId').value;
    const msg = action === 'trash'
        ? 'Chuyển ghi chú này vào thùng rác?'
        : 'Bạn có chắc muốn XÓA VĨNH VIỄN ghi chú này?';
    if (confirm(msg)) {
        fetch('api/delete_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&action=${action}`
        }).then(closeAndReload);
    }
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    fetch('api/restore_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => { alert('Đã khôi phục ghi chú!'); closeAndReload(); });
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&is_pinned=${state}`
    }).then(liveSearch);
}

// ── NHÃN ──────────────────────────────────────────────────────
// SỬA WARN: loadFilterLabels nhận callback để tránh race condition với liveSearch
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list')
        .then(res => res.json())
        .then(ls => {
            const bar = document.getElementById('labelFilterBar');
            bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}"
                onclick="filterLabel(null)" ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>Tất cả</button>`;
            ls.forEach(l => {
                bar.innerHTML += `
                    <div class="btn-group btn-group-sm shadow-sm">
                        <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}"
                            onclick="filterLabel(${l.id})" ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>${escapeHtml(l.name)}</button>
                        <button class="btn btn-outline-danger" onclick="deleteLabel(${l.id})"
                            ${currentViewMode !== 'my_notes' ? 'disabled' : ''}><i class="bi bi-x"></i></button>
                    </div>`;
            });
            if (typeof callback === 'function') callback();
        });
}

function filterLabel(id) {
    if (currentViewMode !== 'my_notes') return;
    currentLabelId = id;
    loadFilterLabels(() => liveSearch());
}

function deleteLabel(id) {
    if (!confirm('Xóa nhãn này?')) return;
    fetch('api/manage_labels.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => {
        if (currentLabelId == id) currentLabelId = null;
        loadFilterLabels(() => liveSearch());
    });
}

function addNewLabel() {
    const n = document.getElementById('newLabelName').value.trim();
    if (!n) return;
    fetch('api/manage_labels.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(n)}`
    }).then(() => {
        document.getElementById('newLabelName').value = '';
        loadFilterLabels();
        if (document.getElementById('noteId').value) refreshLabelSelector();
    });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list')
        .then(res => res.json())
        .then(ls => {
            const s = document.getElementById('labelSelector');
            s.innerHTML = '<option value="">+ Gắn nhãn...</option>';
            ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
        });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`)
        .then(res => res.json())
        .then(ls => {
            const c = document.getElementById('noteLabelsContainer');
            c.innerHTML = '';
            ls.forEach(l => {
                c.innerHTML += `<span class="badge bg-secondary fs-6">${escapeHtml(l.name)}
                    <i class="bi bi-x-circle-fill text-white ms-1 cp" onclick="removeLabel(${nid},${l.id})"></i></span>`;
            });
        });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if (!lid) return;
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=add`
    }).then(() => { loadLabelsForNote(nid); document.getElementById('labelSelector').value = ''; liveSearch(); });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=remove`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ── KHÓA ──────────────────────────────────────────────────────
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (isLockedState) {
        if (!confirm('Gỡ mật khẩu?')) return;
        fetch('api/lock_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `note_id=${id}&action=unlock`
        }).then(() => {
            isLockedState = false;
            document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
            liveSearch();
        });
    } else {
        const p = prompt('Mật khẩu mới:');
        if (!p) return;
        fetch('api/lock_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `note_id=${id}&password=${encodeURIComponent(p)}&action=lock`
        }).then(() => {
            isLockedState = true;
            document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
            liveSearch();
            alert('Đã khóa!');
        });
    }
}

// ── ẢNH ───────────────────────────────────────────────────────
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    document.getElementById('saveStatus').innerText = 'Đang tải ảnh...';
    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
                document.getElementById('saveStatus').innerText = 'Đã tải ảnh';
            } else {
                alert(d.message);
                document.getElementById('saveStatus').innerText = '';
            }
            document.getElementById('imageInput').value = '';
        });
}

function renderImage(path, id, perm) {
    const deleteBtn = perm === 'owner'
        ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle"
              style="width:24px;height:24px;margin-top:-8px;margin-right:-8px;"
              onclick="deleteImage(${id}, this)"><i class="bi bi-x"></i></button>`
        : '';
    document.getElementById('imagePreviewContainer').innerHTML +=
        `<div class="position-relative shadow-sm rounded">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">
            ${deleteBtn}
         </div>`;
}

function deleteImage(id, btn) {
    if (!confirm('Xóa ảnh?')) return;
    fetch('api/delete_image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(res => res.json()).then(d => {
        if (d.success) btn.parentElement.remove();
        else alert(d.message);
    });
}

// ── PROFILE ───────────────────────────────────────────────────
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('previewAvatar').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile() {
    const fd   = new FormData();
    const file = document.getElementById('inputAvatar').files[0];
    if (file) fd.append('avatar', file);
    fd.append('font_size',   document.getElementById('settingFontSize').value);
    fd.append('theme_color', document.getElementById('settingTheme').value);
    fetch('api/update_profile.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) { alert('Đã lưu cấu hình thành công!'); location.reload(); }
            else alert('Có lỗi xảy ra: ' + (data.message || 'Không thể cập nhật'));
        })
        .catch(() => alert('Đã có lỗi kết nối đến server!'));
}

// ── TIỆN ÍCH ──────────────────────────────────────────────────
function setView(v) {
    document.getElementById('notesContainer').className =
        v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function closeAndReload() { noteModal.hide(); liveSearch(); }

function escapeHtml(s) {
    if (!s) return '';
    return s.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>
</body>
</html>