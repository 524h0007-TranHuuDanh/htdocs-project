// ====================== BIẾN TOÀN CỤC ======================
// window.APP_CONFIG được inject từ index.php
const currentUserId   = window.APP_CONFIG?.userId   ?? 0;
const currentUserName = window.APP_CONFIG?.userName  ?? 'User';

let typingTimer, searchTimer;
let currentLabelId      = null;
let currentViewMode     = 'my_notes';
let currentPermission   = 'owner';
let isLockedState       = false;
let currentNoteId       = null;
let passwordModalInstance = null;
let tempOpenData        = null;
let autoRefreshInterval = null;
let autoSaveTimer       = null;
let isSaving            = false;

// Bootstrap modals (khởi tạo sau DOM ready)
let noteModal           = null;
let customAlertModal    = null;
let customConfirmModal  = null;

// ====================== DOM READY (một handler duy nhất) ======================
document.addEventListener('DOMContentLoaded', () => {
    // --- Modals Bootstrap ---
    noteModal          = new bootstrap.Modal(document.getElementById('noteModal'));
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    customAlertModal   = new bootstrap.Modal(document.getElementById('customAlertModal'));
    customConfirmModal = new bootstrap.Modal(document.getElementById('customConfirmModal'));

    // --- Khởi tạo view ---
    setViewMode('my_notes');

    // --- IndexedDB ---
    initIndexedDB();

    // --- Offline / Online events ---
    window.addEventListener('online', () => {
        showToast('Đã kết nối lại. Đang đồng bộ...', 'info');
        setTimeout(syncOfflineNotes, 1000);
    });

    if (!navigator.onLine) {
        setTimeout(loadNotesOfflineFallback, 800);
    }

    // --- Theme preview ---
    const themeSelect = document.getElementById('settingTheme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function () {
            applyTheme(this.value);
        });
    }

    // --- Font size preview ---
    const fontSelect = document.getElementById('settingFontSize');
    if (fontSelect) {
        fontSelect.addEventListener('change', function () {
            document.body.style.fontSize = this.value;
        });
    }

    // --- Force sync content khi blur khỏi noteContent ---
    const noteContentEl = document.getElementById('noteContent');
    if (noteContentEl) {
        noteContentEl.addEventListener('blur', function () {
            if (wsReady && currentNoteIdForWS) {
                _wsSend({
                    type:      'update',
                    note_id:   currentNoteIdForWS,
                    title:     document.getElementById('noteTitle').value || '',
                    content:   this.value || '',
                    version:   parseInt(this.dataset.version) || 1,
                    user_name: currentUserName
                });
            }
        });
    }

    // --- Bảo vệ modal không đóng khi đang gõ ---
    const noteModalEl = document.getElementById('noteModal');
    if (noteModalEl) {
        noteModalEl.addEventListener('hide.bs.modal', function (event) {
            if (document.getElementById('noteId').value &&
                (document.getElementById('noteTitle')   === document.activeElement ||
                 document.getElementById('noteContent') === document.activeElement)) {
                event.preventDefault();
            }
        });
    }
});

// ====================== REALTIME TYPING BROADCAST (80ms debounce) ======================
let realtimeTypingTimer = null;
document.addEventListener('input', function (e) {
    if (window.__remoteUpdating) return;
    if (!wsReady || !currentNoteIdForWS) return;
    if (e.target.id !== 'noteTitle' && e.target.id !== 'noteContent') return;

    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = setTimeout(() => {
        _wsSend({
            type:      'update',
            note_id:   currentNoteIdForWS,
            title:     document.getElementById('noteTitle').value   || '',
            content:   document.getElementById('noteContent').value || '',
            version:   parseInt(document.getElementById('noteContent').dataset.version) || 1,
            user_name: currentUserName
        });
    }, 80);
});

// ====================== VIEW MODE ======================
function setViewMode(mode) {
    const container = document.getElementById('notesContainer');
    container.style.transition  = 'all .25s ease';
    container.style.opacity     = '0';
    container.style.transform   = 'translateY(10px) scale(.98)';

    setTimeout(() => {
        currentViewMode = mode;
        currentLabelId  = null;

        document.getElementById('btnViewShared').style.display  = mode === 'shared'   ? 'none' : 'block';
        document.getElementById('btnViewTrash').style.display   = mode === 'trash'    ? 'none' : 'block';
        document.getElementById('btnViewMyNotes').style.display = mode === 'my_notes' ? 'none' : 'block';

        const viewTitle     = document.getElementById('viewTitle');
        const btnCreate     = document.getElementById('btnCreateNote');
        const addLabelGroup = document.getElementById('addLabelGroup');

        if (mode === 'my_notes') {
            viewTitle.style.display     = 'none';
            btnCreate.style.display     = 'block';
            addLabelGroup.style.display = 'flex';
        } else if (mode === 'trash') {
            viewTitle.innerHTML     = '🗑️ THÙNG RÁC';
            viewTitle.style.display = 'block';
            viewTitle.className     = 'text-danger fw-bold m-0 align-self-center';
            btnCreate.style.display     = 'none';
            addLabelGroup.style.display = 'none';
        } else if (mode === 'shared') {
            viewTitle.innerHTML     = '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';
            viewTitle.style.display = 'block';
            viewTitle.className     = 'text-info fw-bold m-0 align-self-center';
            btnCreate.style.display     = 'none';
            addLabelGroup.style.display = 'none';
        }

        loadFilterLabels(() => {
            liveSearch();
            setTimeout(() => {
                container.style.opacity   = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });

        startAutoRefresh();
    }, 180);
}

// ====================== SEARCH ======================
async function liveSearch() {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(async () => {
        if (!navigator.onLine) {
            const hasOffline = await loadNotesOfflineFallback();
            if (hasOffline) return;
        }

        const q   = encodeURIComponent(document.getElementById('searchInput').value);
        let url   = `api/search.php?q=${q}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;

        fetch(url)
            .then(res => res.json())
            .then(renderNotes)
            .catch(async () => {
                await loadNotesOfflineFallback();
            });
    }, 300);
}

// ====================== RENDER NOTES ======================
function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = {
            trash:    'Thùng rác trống.',
            shared:   'Chưa có ghi chú nào được chia sẻ.',
            my_notes: 'Chưa có ghi chú nào.'
        };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass   = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor    = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName  = n.owner_name || '';
        const permission = n.permission || 'owner';

        let icons = '';
        if (n.is_locked == 1) icons += '<i class="bi bi-lock-fill text-warning me-1" title="Đã khóa"></i>';
        if (ownerName)         icons += '<i class="bi bi-people-fill text-info me-1" title="Được chia sẻ"></i>';
        if (n.is_pinned == 1)  icons += '<i class="bi bi-pin-fill text-danger me-1" title="Đã ghim"></i>';

        const shareInfo = ownerName ? `
            <div class="position-absolute bottom-0 start-0 end-0 px-3 pb-2 d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(ownerName)}</small>
                ${permission === 'edit'
                    ? `<span class="badge bg-success ms-1">✏️ Edit</span>`
                    : `<span class="badge bg-secondary ms-1">👁️ View</span>`}
            </div>` : '';

        const card = document.createElement('div');
        card.className     = 'card note-card';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className        = 'card-body position-relative pb-4';
        body.dataset.id         = n.id;
        body.dataset.title      = n.title      || '';
        body.dataset.content    = n.content    || '';
        body.dataset.isLocked   = n.is_locked  || 0;
        body.dataset.color      = n.color      || '';
        body.dataset.permission = permission;
        body.dataset.ownerName  = ownerName;

        body.addEventListener('click', () => handleNoteOpen(
            parseInt(body.dataset.id),
            body.dataset.title,
            body.dataset.content,
            parseInt(body.dataset.isLocked),
            body.dataset.color,
            body.dataset.permission,
            body.dataset.ownerName
        ));

        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                     onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                     <i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1">
                ${icons} ${escapeHtml(n.title) || 'Không tiêu đề'}
            </h5>
            <p class="card-text text-muted text-truncate"
               style="white-space:pre-wrap; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;">
               ${escapeHtml(n.content) || 'Không có nội dung...'}
            </p>
            ${shareInfo}
        `;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    currentNoteId     = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        document.getElementById('passwordModalTitle').textContent = '🔒 Ghi chú đã bị khóa';
        document.getElementById('notePasswordInput').value        = '';
        document.getElementById('passwordError').style.display    = 'none';
        window.tempOpenData = { id, title, content, color, permission, ownerName };
        passwordModalInstance.show();
        setTimeout(() => document.getElementById('notePasswordInput').focus(), 500);
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    const password = document.getElementById('notePasswordInput').value.trim();
    const errorEl  = document.getElementById('passwordError');
    if (!password) {
        errorEl.textContent   = 'Vui lòng nhập mật khẩu!';
        errorEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append('note_id',  currentNoteId);
    fd.append('password', password);

    fetch('api/verify_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                passwordModalInstance.hide();
                isLockedState = true;
                openNoteModal(
                    window.tempOpenData.id,
                    d.title, d.content, d.color,
                    d.permission,
                    window.tempOpenData.ownerName
                );
            } else {
                errorEl.textContent   = d.message || 'Mật khẩu không đúng!';
                errorEl.style.display = 'block';
                document.getElementById('notePasswordInput').value = '';
                document.getElementById('notePasswordInput').focus();
            }
        });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    currentNoteId     = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl           = document.getElementById('noteContent');
    contentEl.dataset.version = '1';

    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText            = '';

    // --- Áp dụng màu ghi chú ---
    const modalWrapper  = document.getElementById('modalContentWrapper');
    const resolvedColor = (color && color.trim() !== '')
        ? color
        : (document.documentElement.style.getPropertyValue('--note-default-color') || '#ffffff');
    modalWrapper.style.backgroundColor = resolvedColor;
    modalWrapper.style.setProperty('--note-individual-color', resolvedColor);

    // --- Ẩn tất cả các section trước ---
    ['toolsSection','colorSection','shareManagerSection','btnTrashNote','btnRestoreNote','btnDeletePermanent']
        .forEach(el => { const e = document.getElementById(el); if (e) e.style.display = 'none'; });

    const isTrash  = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice   = document.getElementById('sharedNotice');
    const wsBadge  = document.getElementById('wsStatusBadge');

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display     = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
        if (wsBadge) wsBadge.style.display = 'none';

    } else if (isShared) {
        notice.style.display = 'block';
        notice.innerHTML = `
            <strong>Được chia sẻ bởi:</strong> <b>${escapeHtml(ownerName)}</b><br>
            <strong>Quyền:</strong> <b>${permission === 'edit' ? '✅ Có thể chỉnh sửa' : '👁️ Chỉ xem'}</b>
        `;
        document.getElementById('noteTitle').readOnly   = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';
        if (id) {
            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(r => r.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
        }
        if (wsBadge) wsBadge.style.display = permission === 'edit' ? 'inline-flex' : 'none';

    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = false;
        document.getElementById('noteContent').readOnly = false;

        if (id) {
            document.getElementById('toolsSection').style.display        = 'block';
            document.getElementById('colorSection').style.display        = 'block';
            document.getElementById('shareManagerSection').style.display = 'block';
            document.getElementById('btnTrashNote').style.display        = 'block';

            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(r => r.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
            if (wsBadge) wsBadge.style.display = 'inline-flex';
        }
    }

    // --- Lấy version mới nhất từ server ---
    if (id) {
        fetch(`api/get_notes.php?note_id=${id}`)
            .then(r => r.json())
            .then(note => { if (note && note.version) contentEl.dataset.version = note.version; })
            .catch(() => {});
    }

    // --- Placeholder cho note mới ---
    if (!id) {
        document.getElementById('noteTitle').placeholder   = 'Nhập tiêu đề ghi chú...';
        document.getElementById('noteContent').placeholder = 'Nhập nội dung ghi chú của bạn...';
    }

    // --- Khởi động realtime (một lần duy nhất) ---
    if (id && (permission === 'edit' || permission === 'owner')) {
        startRealtimeForNote(id, permission);
    }

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
    stopRealtime();
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
// Tích hợp: offline fallback + WebSocket broadcast + xử lý conflict
// =========================================================
function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    if (isSaving) return;

    const noteId  = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;

    if (!noteId && !title && !content) return;

    document.getElementById('saveStatus').innerHTML = '<i class="bi bi-hourglass-split"></i> Đang lưu...';

    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {

        // --- OFFLINE: lưu cục bộ ngay lập tức ---
        if (!navigator.onLine) {
            const noteData = {
                id:         noteId || 'temp_' + Date.now(),
                title,
                content,
                version:    parseInt(document.getElementById('noteContent').dataset.version) || 1,
                updated_at: new Date().toISOString()
            };
            saveNoteOffline(noteData);
            document.getElementById('saveStatus').innerHTML =
                '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Đã lưu offline</span>';
            showToast('Không có mạng. Ghi chú đã lưu cục bộ.', 'warning');
            return;
        }

        // --- ONLINE: gửi lên server ---
        isSaving = true;
        const fd = new FormData();
        fd.append('id',         noteId);
        fd.append('title',      title);
        fd.append('content',    content);
        fd.append('version',    document.getElementById('noteContent').dataset.version || 1);
        fd.append('csrf_token', window.APP_CONFIG?.csrf_token || '');

        fetch('api/save_note.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(d => {
                isSaving = false;
                const statusEl  = document.getElementById('saveStatus');
                const contentEl = document.getElementById('noteContent');

                // -----------------------------------------------
                // FIX: Xử lý conflict phiên bản
                // Server trả về conflict=true khi client quá cũ.
                // Cập nhật version + nội dung mới nhất, rồi tự
                // retry sau 1 giây để lưu lại.
                // -----------------------------------------------
                if (!d.success && d.conflict) {
                    contentEl.dataset.version = d.version;

                    // Chỉ cập nhật nội dung nếu user KHÔNG đang gõ
                    if (document.activeElement !== contentEl && d.latest_content !== undefined) {
                        contentEl.value = d.latest_content;
                    }
                    if (document.activeElement !== document.getElementById('noteTitle') && d.latest_title !== undefined) {
                        document.getElementById('noteTitle').value = d.latest_title;
                    }

                    statusEl.innerHTML = '<i class="bi bi-arrow-clockwise text-warning"></i> Đồng bộ xong';
                    showToast('Đã cập nhật phiên bản mới nhất, đang lưu lại...', 'warning');

                    // Retry sau 1 giây với version mới
                    setTimeout(autoSave, 1000);
                    return;
                }

                if (d.success) {
                    statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu';

                    if (!noteId && d.note_id) {
                        document.getElementById('noteId').value = d.note_id;
                        currentNoteId = d.note_id;
                        document.getElementById('toolsSection').style.display        = 'block';
                        document.getElementById('colorSection').style.display        = 'block';
                        document.getElementById('shareManagerSection').style.display = 'block';
                        document.getElementById('btnTrashNote').style.display        = 'block';
                    }

                    if (d.version) {
                        contentEl.dataset.version = d.version;
                    }

                    liveSearch();
                } else {
                    statusEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Lỗi lưu';
                    if (d.message) showToast(d.message, 'danger');
                }
            })
            .catch(() => {
                isSaving = false;
                document.getElementById('saveStatus').innerHTML =
                    '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Lưu offline</span>';
                const noteData = {
                    id:         noteId || 'temp_' + Date.now(),
                    title,
                    content,
                    version:    parseInt(document.getElementById('noteContent').dataset.version) || 1,
                    updated_at: new Date().toISOString()
                };
                saveNoteOffline(noteData);
                showToast('Không kết nối mạng. Ghi chú đã được lưu cục bộ.', 'warning');
            });
    }, 800);
}

// ====================== CHIA SẺ ======================
function shareNote() {
    const noteId = document.getElementById('noteId').value;
    const input  = document.getElementById('share_input').value.trim();
    const perm   = document.getElementById('sharePermission').value;

    if (!noteId) return showAlert('Vui lòng lưu ghi chú trước khi chia sẻ!', 'warning');
    if (!input)  return showAlert('Vui lòng nhập email!', 'warning');

    const fd = new FormData();
    fd.append('note_id',    noteId);
    fd.append('permission', perm);
    fd.append('share_with', input);
    fd.append('csrf_token', window.APP_CONFIG?.csrf_token || '');

    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.message || 'Đã thực hiện chia sẻ', d.success ? 'success' : 'danger');
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
                liveSearch();
            }
        })
        .catch(() => showAlert('Lỗi khi chia sẻ!', 'danger'));
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
                        <span><i class="bi bi-person-check text-success"></i> ${escapeHtml(u.display_name)}
                            <small>(${u.permission})</small></span>
                        <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
                    </li>`;
            });
        });
}

function revokeShare(shareId) {
    showConfirm('Thu hồi quyền chia sẻ này?', () => {
        const fd = new FormData();
        fd.append('share_id', shareId);
        fetch('api/revoke_share.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                loadSharedUsers(document.getElementById('noteId').value);
                showAlert(data.message || 'Đã thu hồi quyền chia sẻ', data.success ? 'success' : 'danger');
            })
            .catch(() => showAlert('Lỗi khi thu hồi quyền!', 'danger'));
    });
}

// ====================== KHÓA GHI CHÚ ======================
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    if (isLockedState) {
        _showLockActionPicker(id);
    } else {
        _showLockSetModal(id);
    }
}

function _showLockSetModal(id) {
    _openPasswordModal({
        title: '🔒 Đặt mật khẩu cho ghi chú',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');

            const fd = new FormData();
            fd.append('note_id',          id);
            fd.append('action',           'lock');
            fd.append('password',         pw);
            fd.append('confirm_password', pw2);
            fd.append('csrf_token',       window.APP_CONFIG?.csrf_token || '');

            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        isLockedState = true;
                        document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
                        liveSearch();
                        return true;
                    }
                    showError(d.message || 'Không thể đặt khóa!');
                    return false;
                });
        }
    });
}

function _showLockActionPicker(id) {
    _openPasswordModal({
        title: '🔒 Ghi chú đang được khóa',
        fields: [
            { id: 'pm_old_pw', placeholder: 'Nhập mật khẩu hiện tại', type: 'password' }
        ],
        actions: [
            { label: 'Gỡ khóa',      style: 'btn-warning', value: 'unlock' },
            { label: 'Đổi mật khẩu', style: 'btn-primary', value: 'change' }
        ],
        onConfirm(vals, showError, actionValue) {
            const [oldPw] = vals;
            if (!oldPw) return showError('Vui lòng nhập mật khẩu hiện tại!');

            const fd = new FormData();
            fd.append('note_id',      id);
            fd.append('old_password', oldPw);
            fd.append('csrf_token',   window.APP_CONFIG?.csrf_token || '');

            if (actionValue === 'unlock') {
                fd.append('action', 'unlock');
                return fetch('api/lock_note.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            isLockedState = false;
                            document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
                            liveSearch();
                            return true;
                        }
                        showError(d.message || 'Mật khẩu không đúng!');
                        return false;
                    });
            } else {
                fd.append('action', 'verify');
                return fetch('api/lock_note.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { showError(d.message || 'Mật khẩu không đúng!'); return false; }
                        passwordModalInstance.hide();
                        setTimeout(() => _showChangePasswordModal(id, oldPw), 300);
                        return false;
                    });
            }
        }
    });
}

function _showChangePasswordModal(id, oldPw) {
    _openPasswordModal({
        title: '🔑 Đặt mật khẩu mới',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');

            const fd = new FormData();
            fd.append('note_id',          id);
            fd.append('action',           'change');
            fd.append('old_password',     oldPw);
            fd.append('password',         pw);
            fd.append('confirm_password', pw2);
            fd.append('csrf_token',       window.APP_CONFIG?.csrf_token || '');

            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { liveSearch(); return true; }
                    showError(d.message || 'Không thể đổi mật khẩu!');
                    return false;
                });
        }
    });
}

function _openPasswordModal(config) {
    const titleEl  = document.getElementById('passwordModalTitle');
    const bodyEl   = document.getElementById('passwordModal').querySelector('.modal-body');
    const footerEl = document.getElementById('passwordModal').querySelector('.modal-footer');

    titleEl.textContent = config.title;
    bodyEl.innerHTML = config.fields.map(f =>
        `<input id="${f.id}" type="${f.type}" class="form-control mb-2" placeholder="${f.placeholder}" autocomplete="off">`
    ).join('') + `<div id="pm_error" class="text-danger small mt-1" style="display:none;"></div>`;

    const actions = config.actions || [{ label: 'Xác nhận', style: 'btn-primary', value: 'confirm' }];
    footerEl.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>` +
        actions.map(a => `<button type="button" class="btn ${a.style} pm-action-btn" data-action="${a.value}">${a.label}</button>`).join('');

    const errorEl = document.getElementById('pm_error');

    function showError(msg) {
        errorEl.textContent   = msg;
        errorEl.style.display = 'block';
        footerEl.querySelectorAll('.pm-action-btn').forEach(b => {
            b.disabled    = false;
            b.textContent = b.dataset.origLabel;
        });
    }

    footerEl.querySelectorAll('.pm-action-btn').forEach(btn => {
        btn.dataset.origLabel = btn.textContent;
        btn.addEventListener('click', function () {
            errorEl.style.display = 'none';
            const vals = config.fields.map(f => document.getElementById(f.id).value.trim());
            footerEl.querySelectorAll('.pm-action-btn').forEach(b => { b.disabled = true; });
            btn.textContent = 'Đang xử lý...';

            Promise.resolve(config.onConfirm(vals, showError, btn.dataset.action))
                .then(shouldClose => { if (shouldClose === true) passwordModalInstance.hide(); })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    document.getElementById('passwordModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById(config.fields[0].id)?.focus();
        this.removeEventListener('shown.bs.modal', onShown);
    });

    passwordModalInstance.show();
}

// ====================== XÓA GHI CHÚ ======================
function deleteNote(action) {
    const id = document.getElementById('noteId').value;
    if (!id) return;

    const isPermanent = action === 'permanent';
    const title       = isPermanent ? 'Xóa vĩnh viễn ghi chú?' : 'Chuyển vào thùng rác?';
    const bodyText    = isPermanent
        ? 'Hành động này <strong>không thể hoàn tác</strong>.'
        : 'Ghi chú sẽ được chuyển vào thùng rác.';

    document.getElementById('deleteModalTitle').innerHTML = `<i class="bi bi-trash3"></i> ${title}`;
    document.getElementById('deleteModalBody').innerHTML  = `<p>${bodyText}</p>`;

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();

    document.getElementById('confirmDeleteBtn').onclick = function () {
        deleteModal.hide();
        if (isLockedState) {
            _deleteNoteWithPassword(id, action);
        } else {
            _doDeleteNote(id, action);
        }
    };
}

function _doDeleteNote(id, action) {
    const fd = new FormData();
    fd.append('id',         id);
    fd.append('action',     action);
    fd.append('csrf_token', window.APP_CONFIG?.csrf_token || '');

    fetch('api/delete_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                showToast(d.message || 'Đã thực hiện thành công', 'success');
                closeAndReload();
            } else if (d.require_password) {
                _deleteNoteWithPassword(id, action);
            } else {
                showToast(d.message || 'Không thể xóa ghi chú!', 'danger');
            }
        })
        .catch(() => showToast('Lỗi kết nối khi xóa ghi chú!', 'danger'));
}

function _deleteNoteWithPassword(id, action) {
    const titleEl    = document.getElementById('passwordModalTitle');
    const inputEl    = document.getElementById('notePasswordInput');
    const errorEl    = document.getElementById('passwordError');
    const confirmBtn = document.getElementById('passwordModalConfirmBtn');

    titleEl.textContent   = '🔒 Nhập mật khẩu để xác nhận xóa';
    inputEl.value         = '';
    errorEl.style.display = 'none';

    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.onclick = function () {
        const pw = inputEl.value.trim();
        if (!pw) {
            errorEl.textContent   = 'Vui lòng nhập mật khẩu!';
            errorEl.style.display = 'block';
            inputEl.focus();
            return;
        }
        newBtn.disabled    = true;
        newBtn.textContent = 'Đang xác nhận...';
        errorEl.style.display = 'none';

        const fd = new FormData();
        fd.append('id',              id);
        fd.append('action',          action);
        fd.append('delete_password', pw);
        fd.append('csrf_token',      window.APP_CONFIG?.csrf_token || '');

        fetch('api/delete_note.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(d => {
                if (d.success) {
                    showToast(d.message || 'Đã xóa thành công', 'success');
                    passwordModalInstance.hide();
                    closeAndReload();
                } else {
                    errorEl.textContent   = d.message || 'Mật khẩu không đúng!';
                    errorEl.style.display = 'block';
                    inputEl.value         = '';
                    inputEl.focus();
                    newBtn.disabled    = false;
                    newBtn.textContent = 'Xác nhận';
                }
            })
            .catch(() => {
                errorEl.textContent   = 'Lỗi kết nối!';
                errorEl.style.display = 'block';
                newBtn.disabled    = false;
                newBtn.textContent = 'Xác nhận';
            });
    };

    passwordModalInstance.show();
    setTimeout(() => inputEl.focus(), 300);
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/restore_note.php', { method: 'POST', body: fd })
        .then(() => { showAlert('Khôi phục thành công!', 'success'); closeAndReload(); });
}

// ====================== WEBSOCKET REALTIME ======================
let ws                 = null;
let wsReconnectTimer   = null;
let wsReady            = false;
let currentNoteIdForWS = null;
let _pollInterval      = null;

const WS_HOST = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':8080';

function connectWebSocket() {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
    try {
        ws = new WebSocket(WS_HOST);
    } catch (e) {
        console.warn('WebSocket không khả dụng, dùng fallback polling.');
        _startFallbackPolling();
        return;
    }

    ws.onopen = () => {
        clearTimeout(wsReconnectTimer);
        ws.send(JSON.stringify({ type: 'auth', user_id: currentUserId, user_name: currentUserName }));
        _setWsStatus('connecting');
    };

    ws.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);

            if (data.type === 'auth_success') {
                wsReady = true;
                _setWsStatus('online');
                if (currentNoteIdForWS) _wsSend({ type: 'join_note', note_id: currentNoteIdForWS });
            }

            if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');

                const isEditingTitle   = document.activeElement === titleEl;
                const isEditingContent = document.activeElement === contentEl;

                // FIX: cập nhật version ngay khi nhận WS update
                // (cả từ typing broadcast lẫn from_save)
                if (data.version) {
                    contentEl.dataset.version = data.version;
                }

                if (data.title !== undefined && !isEditingTitle) {
                    titleEl.value = data.title;
                }

                if (data.content !== undefined) {
                    const currentContent  = contentEl.value    || '';
                    const incomingContent = String(data.content);

                    if (!isEditingContent) {
                        window.__remoteUpdating = true;
                        contentEl.value         = incomingContent;
                        window.__remoteUpdating = false;
                    } else {
                        const isDeleting   = incomingContent.length < currentContent.length;
                        const tooDifferent = Math.abs(incomingContent.length - currentContent.length) > 5;

                        if (isDeleting || tooDifferent) {
                            const cursorPos         = contentEl.selectionStart;
                            window.__remoteUpdating = true;
                            contentEl.value         = incomingContent;
                            window.__remoteUpdating = false;
                            try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
                        }
                    }
                }

                _showTypingIndicator(data.user_name);
            }

            if (data.type === 'presence' && data.note_id == currentNoteIdForWS) {
                _renderPresence(data.users);
            }
        } catch (e) {
            console.error('WS parse error:', e);
        }
    };

    ws.onclose = () => {
        wsReady          = false;
        _setWsStatus('offline');
        wsReconnectTimer = setTimeout(connectWebSocket, 3000);
    };

    ws.onerror = () => { _setWsStatus('offline'); };
}

function _wsSend(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj));
}

function _startFallbackPolling() {
    if (_pollInterval) return;
    _setWsStatus('polling');
    _pollInterval = setInterval(() => {
        if (!currentNoteIdForWS) return;
        fetch(`api/get_notes.php?note_id=${currentNoteIdForWS}`)
            .then(r => r.ok ? r.json() : null)
            .then(note => {
                if (!note) return;
                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');
                if (document.activeElement !== titleEl)   titleEl.value   = note.title   ?? '';
                if (document.activeElement !== contentEl) contentEl.value = note.content ?? '';
                if (note.version) contentEl.dataset.version = note.version;
            })
            .catch(() => {});
    }, 4000);
}

function _stopFallbackPolling() { clearInterval(_pollInterval); _pollInterval = null; }

function _setWsStatus(state) {
    const el = document.getElementById('wsStatusBadge');
    if (!el) return;
    const map = {
        online:     { text: '● Trực tuyến',      cls: 'bg-success'   },
        offline:    { text: '● Mất kết nối',      cls: 'bg-danger'    },
        connecting: { text: '● Đang kết nối…',   cls: 'bg-warning'   },
        polling:    { text: '● Chế độ dự phòng', cls: 'bg-secondary' }
    };
    const s        = map[state] || map.offline;
    el.textContent = s.text;
    el.className   = `badge ${s.cls} ms-2 small`;
}

function _renderPresence(users) {
    const el = document.getElementById('wsPresenceBar');
    if (!el) return;
    const others = users.filter(u => u !== currentUserName);
    el.innerHTML = others.length
        ? `<i class="bi bi-people-fill"></i> Đang xem cùng: <strong>${others.map(u => escapeHtml(u)).join(', ')}</strong>`
        : '';
}

let _typingTimer = null;
function _showTypingIndicator(userName) {
    const el = document.getElementById('wsTypingIndicator');
    if (!el || userName === currentUserName) return;
    el.textContent   = `✏️ ${escapeHtml(userName)} đang chỉnh sửa…`;
    el.style.display = 'block';
    clearTimeout(_typingTimer);
    _typingTimer = setTimeout(() => { el.style.display = 'none'; }, 2500);
}

function startRealtimeForNote(noteId, permission) {
    if (permission !== 'edit' && permission !== 'owner') return;
    currentNoteIdForWS = noteId;
    connectWebSocket();
    if (wsReady) _wsSend({ type: 'join_note', note_id: noteId });
}

function stopRealtime() {
    if (currentNoteIdForWS) _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
    currentNoteIdForWS = null;
    _stopFallbackPolling();
    const p = document.getElementById('wsPresenceBar');
    const t = document.getElementById('wsTypingIndicator');
    if (p) p.textContent   = '';
    if (t) { t.textContent = ''; t.style.display = 'none'; }
}

// ====================== ẢNH ======================
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('image',   f);
    fd.append('note_id', nid);

    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
            } else {
                showAlert(d.message || 'Không thể upload ảnh', 'danger');
            }
            document.getElementById('imageInput').value = '';
        })
        .catch(() => showAlert('Lỗi kết nối khi upload ảnh!', 'danger'));
}

function renderImage(path, id, perm) {
    const del = perm === 'owner'
        ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle"
             style="width:24px;height:24px;margin:-8px -8px 0 0;"
             onclick="deleteImage(${id},this)"><i class="bi bi-x"></i></button>`
        : '';
    document.getElementById('imagePreviewContainer').innerHTML +=
        `<div class="position-relative shadow-sm rounded">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">${del}
         </div>`;
}

function deleteImage(id, btn) {
    showConfirm('Xóa ảnh này?', () => {
        fetch('api/delete_image.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `id=${id}`
        }).then(r => r.json()).then(d => { if (d.success) btn.parentElement.remove(); });
    });
}

// ====================== LABEL ======================
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list')
        .then(r => r.json())
        .then(ls => {
            const bar = document.getElementById('labelFilterBar');
            bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}"
                onclick="filterLabel(null)">Tất cả</button>`;
            ls.forEach(l => {
                bar.innerHTML += `
                    <div class="btn-group btn-group-sm">
                        <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}"
                            onclick="filterLabel(${l.id})">${escapeHtml(l.name)}</button>
                        <button class="btn btn-outline-secondary"
                            onclick="event.stopPropagation();renameLabel(${l.id},'${escapeHtml(l.name).replace(/'/g, "\\'")}')">
                            <i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger"
                            onclick="event.stopPropagation();deleteLabel(${l.id})">
                            <i class="bi bi-x"></i></button>
                    </div>`;
            });
            if (callback) callback();
        });
}

function filterLabel(id) { currentLabelId = id; loadFilterLabels(() => liveSearch()); }

function addNewLabel() {
    const name = document.getElementById('newLabelName').value.trim();
    if (!name) return;
    fetch('api/manage_labels.php?action=add', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `name=${encodeURIComponent(name)}`
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `id=${id}&name=${encodeURIComponent(newName.trim())}`
    }).then(() => loadFilterLabels(() => liveSearch()));
}

function deleteLabel(id) {
    showConfirm('Xóa nhãn này?', () => {
        fetch('api/manage_labels.php?action=delete', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `id=${id}`
        }).then(() => {
            if (currentLabelId == id) currentLabelId = null;
            loadFilterLabels(() => liveSearch());
        });
    });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list')
        .then(r => r.json())
        .then(ls => {
            const s = document.getElementById('labelSelector');
            s.innerHTML = '<option value="">+ Nhãn</option>';
            ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
        });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`)
        .then(r => r.json())
        .then(ls => {
            const c = document.getElementById('noteLabelsContainer');
            c.innerHTML = '';
            ls.forEach(l => c.innerHTML +=
                `<span class="badge bg-secondary">${escapeHtml(l.name)}
                 <i class="bi bi-x-circle-fill cp" onclick="removeLabel(${nid},${l.id})"></i></span>`);
        });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if (!lid) return;
    fetch('api/set_note_label.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `note_id=${nid}&label_id=${lid}&action=add`
    }).then(() => {
        loadLabelsForNote(nid);
        liveSearch();
        document.getElementById('labelSelector').value = '';
    });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `note_id=${nid}&label_id=${lid}&action=remove`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== PROFILE / SETTINGS ======================
function saveProfile() {
    const fd = new FormData();

    const avatarFile = document.getElementById('inputAvatar').files[0];
    if (avatarFile) fd.append('avatar', avatarFile);

    const fontSize  = document.getElementById('settingFontSize').value;
    const theme     = document.getElementById('settingTheme').value;
    const noteColor = document.getElementById('settingNoteColor').value;

    fd.append('font_size',   fontSize);
    fd.append('theme_color', theme);
    fd.append('note_color',  noteColor);
    fd.append('csrf_token',  window.APP_CONFIG?.csrf_token || '');

    applyTheme(theme);
    document.documentElement.style.fontSize = fontSize;
    document.body.style.fontSize            = fontSize;
    document.documentElement.style.setProperty('--note-default-color', noteColor);

    fetch('api/update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (avatarFile) {
                    location.reload();
                } else {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                    if (modal) modal.hide();
                    showToast('Đã lưu thay đổi thành công!', 'success');
                }
            } else {
                showAlert(data.message || 'Lỗi cập nhật!', 'danger');
            }
        })
        .catch(() => showAlert('Lỗi kết nối!', 'danger'));
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatar').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('noteapp_theme', theme);
}

// ====================== TIỆN ÍCH ======================
function setView(v) {
    document.getElementById('notesContainer').className =
        v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `id=${id}&is_pinned=${state}`
    }).then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) { showAlert('Vui lòng lưu ghi chú trước khi đổi màu!', 'warning'); return; }

    const fd = new FormData();
    fd.append('id',         id);
    fd.append('color',      color || '');
    fd.append('csrf_token', window.APP_CONFIG?.csrf_token || '');

    fetch('api/change_color.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                const modalWrapper = document.getElementById('modalContentWrapper');
                modalWrapper.style.backgroundColor = color || '';
                modalWrapper.style.setProperty('--note-individual-color', color || '');
                liveSearch();
                showAlert('Đã đổi màu ghi chú thành công', 'success');
            } else {
                showAlert('Không thể đổi màu ghi chú!', 'danger');
            }
        })
        .catch(() => showAlert('Lỗi kết nối!', 'danger'));
}

function formatRelativeTime(datetime) {
    if (!datetime) return '';
    const date    = new Date(datetime);
    const diffMin = Math.floor((new Date() - date) / 60000);
    if (diffMin < 1)    return 'Vừa xong';
    if (diffMin < 60)   return diffMin + ' phút trước';
    if (diffMin < 1440) return Math.floor(diffMin / 60) + ' giờ trước';
    return date.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' });
}

function escapeHtml(s) {
    if (!s) return '';
    return s.toString()
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}

// ====================== TOAST ======================
function showToast(message, type = 'success') {
    const colorMap = { success: 'success', warning: 'warning', danger: 'danger', info: 'info' };
    const iconMap  = { success: '✅', warning: '⚠️', danger: '❌', info: 'ℹ️' };
    const bg       = colorMap[type] || 'secondary';
    const icon     = iconMap[type]  || 'ℹ️';

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${bg} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${icon} ${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 2500 });
    bsToast.show();
    setTimeout(() => toast.remove(), 2800);
}

// ====================== CUSTOM MODAL ======================
function showAlert(message, type = 'info') {
    const titleEl = document.getElementById('customAlertTitle');
    const bodyEl  = document.getElementById('customAlertBody');
    const btnEl   = document.getElementById('customAlertBtn');

    const map = {
        success: { html: `<i class="bi bi-check-circle-fill text-success"></i> Thành công`, cls: 'btn btn-success px-4' },
        danger:  { html: `<i class="bi bi-x-circle-fill text-danger"></i> Lỗi`,             cls: 'btn btn-danger px-4'  },
        error:   { html: `<i class="bi bi-x-circle-fill text-danger"></i> Lỗi`,             cls: 'btn btn-danger px-4'  },
        warning: { html: `<i class="bi bi-exclamation-triangle-fill text-warning"></i> Cảnh báo`, cls: 'btn btn-warning px-4' },
        info:    { html: `<i class="bi bi-info-circle"></i> Thông báo`,                     cls: 'btn btn-primary px-4' }
    };
    const m = map[type] || map.info;
    titleEl.innerHTML = m.html;
    btnEl.className   = m.cls;
    bodyEl.innerHTML  = `<p class="mb-0">${message}</p>`;
    customAlertModal.show();
}

function showConfirm(message, onConfirm, onCancel = null) {
    document.getElementById('customConfirmBody').innerHTML = `<p>${message}</p>`;

    const okBtn     = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');

    okBtn.replaceWith(okBtn.cloneNode(true));
    cancelBtn.replaceWith(cancelBtn.cloneNode(true));

    document.getElementById('confirmOkBtn').onclick = () => {
        customConfirmModal.hide();
        if (onConfirm) onConfirm();
    };
    document.getElementById('confirmCancelBtn').onclick = () => {
        customConfirmModal.hide();
        if (onCancel) onCancel();
    };

    customConfirmModal.show();
}

// ====================== INDEXEDDB / OFFLINE ======================
let db = null;

function initIndexedDB() {
    const request = indexedDB.open('NoteAppDB', 3);

    request.onupgradeneeded = function (event) {
        db = event.target.result;
        if (!db.objectStoreNames.contains('notes')) {
            const store = db.createObjectStore('notes', { keyPath: 'id' });
            store.createIndex('updated_at', 'updated_at');
            store.createIndex('isTemp',     'isTemp');
        }
    };

    request.onsuccess = function (event) {
        db = event.target.result;
        console.log('[IndexedDB] Initialized v3');
    };

    request.onerror = function (event) {
        console.error('[IndexedDB] Error:', event.target.error);
    };
}

function isNoteTemp(id) {
    return typeof id === 'string' && id.startsWith('temp_');
}

function saveNoteOffline(note) {
    if (!db) return;
    const noteToSave = {
        ...note,
        isTemp:     isNoteTemp(note.id),
        syncStatus: 'pending',
        updated_at: new Date().toISOString()
    };
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(noteToSave);
    console.log(`[Offline] Saved note ${note.id}`);
}

async function getAllOfflineNotes() {
    if (!db) return [];
    return new Promise((resolve) => {
        const tx    = db.transaction('notes', 'readonly');
        const store = tx.objectStore('notes');
        const req   = store.getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => resolve([]);
    });
}

async function syncOfflineNotes() {
    if (!navigator.onLine || !db) return;

    const offlineNotes = await getAllOfflineNotes();
    if (offlineNotes.length === 0) return;
    console.log(`[Offline Sync] Found ${offlineNotes.length} notes`);

    let syncedCount = 0;
    for (const note of offlineNotes) {
        try {
            const fd = new FormData();
            fd.append('id',         isNoteTemp(note.id) ? 0 : note.id);
            fd.append('title',      note.title   || '');
            fd.append('content',    note.content || '');
            fd.append('version',    note.version || 1);
            fd.append('csrf_token', window.APP_CONFIG?.csrf_token || '');

            const res  = await fetch('api/save_note.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                syncedCount++;
                const delTx = db.transaction(['notes'], 'readwrite');
                delTx.objectStore('notes').delete(note.id);
                console.log(`[Offline] Synced ${note.id} → ${data.note_id}`);
            }
        } catch (err) {
            console.error('Sync failed for note', note.id, err);
        }
    }

    if (syncedCount > 0) {
        showToast(`Đã đồng bộ ${syncedCount} ghi chú từ chế độ offline`, 'success');
        setTimeout(liveSearch, 800);
    }
}

async function loadNotesOfflineFallback() {
    const offlineNotes = await getAllOfflineNotes();
    if (offlineNotes.length > 0) {
        offlineNotes.forEach(n => {
            if (isNoteTemp(n.id)) n.title = '📴 ' + (n.title || 'Ghi chú tạm');
        });
        renderNotes(offlineNotes);
        showToast('Đang hiển thị ghi chú offline', 'warning');
        return true;
    }
    return false;
}