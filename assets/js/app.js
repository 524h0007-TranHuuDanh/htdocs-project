// ====================== BIẾN TOÀN CỤC ======================
// window.APP_CONFIG được inject từ index.php
const currentUserId   = window.APP_CONFIG?.userId   ?? 0;
const currentUserName = window.APP_CONFIG?.userName  ?? 'User';

let renameLabelId = null;
let renameLabelCurrentName = '';
let typingTimer, searchTimer;
let currentLabelId      = null;
let currentViewMode     = 'my_notes';
let currentPermission   = 'owner';
let isLockedState       = false;
let currentNoteId       = null;
let passwordModalInstance = null;
let tempOpenData        = null;
let autoRefreshInterval = null;
let autoSaveTimer          = null;
let autoSaveRetryTimer     = null;
let autoSaveBusyTimer      = null;
let autoSaveInFlightSeq    = 0;
let lastAutoSavePersistSig = '';
let isSaving               = false;
let bulkMode = false;
let bulkShareModal = null;
let selectedNotes = new Set(); // lưu id
let currentNotesList = []; // lưu danh sách note hiện tại để kiểm tra khóa
/** Coalesces list refresh after rapid autosaves (single search request). */
let liveSearchAfterSaveTimer = null;
/** True while applying server title/content+version after HTTP conflict; blocks autosave to avoid stale POSTs. */
let noteConflictResolutionLock = false;
/** True while GET api/get_notes.php (note_id) is in flight for an opened note; blocks autosave until response. */
let noteVersionLoadPending = false;
let noteVersionFetchGen    = 0;

/** Offline queue sync: avoids tight retry loops and exposes status to the UI layer. */
let offlineSyncIsRunning = false;
const OFFLINE_SYNC_BASE_DELAY_MS = 2000;
const OFFLINE_SYNC_MAX_DELAY_MS  = 120000;

// Bootstrap modals (khởi tạo sau DOM ready)
let noteModal           = null;
let customAlertModal    = null;
let customConfirmModal  = null;
let resetChoiceModal = null; // Thêm dòng này
let profileSubScreen    = null; // Biến mới cho profile

function resetPasswordModalToDefault() {
    const modal = document.getElementById('passwordModal');
    if (!modal) return;

    const bodyEl = modal.querySelector('.modal-body');
    const footerEl = modal.querySelector('.modal-footer');

    if (!bodyEl || !footerEl) return;

    // Khôi phục body về trạng thái nhập mật khẩu mặc định
    bodyEl.innerHTML = `
        <input type="password" id="notePasswordInput" class="form-control" placeholder="Nhập mật khẩu..." autocomplete="current-password">
        <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
    `;

    // Khôi phục footer về nút mặc định
    footerEl.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" id="passwordModalConfirmBtn" class="btn btn-primary">Xác nhận</button>
    `;

    // Gắn lại sự kiện cho nút xác nhận
    const confirmBtn = document.getElementById('passwordModalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = submitNotePassword;
    }
}
function appendCsrfToken(formData) {
    formData.append('csrf_token', window.APP_CONFIG?.csrf_token || '');
}

/** application/x-www-form-urlencoded bodies */
function appendCsrfUrlEncoded(body) {
    const raw = window.APP_CONFIG?.csrf_token || '';
    const base = body == null ? '' : String(body);
    const sep    = base.trim() !== '' ? '&' : '';
    return `${base}${sep}csrf_token=${encodeURIComponent(raw)}`;
}

function getNoteContentVersion() {
    const contentEl = document.getElementById('noteContent');
    const raw = contentEl?.dataset.version;
    if (raw === undefined || raw === '') return NaN;
    const v = parseInt(raw, 10);
    return Number.isFinite(v) ? v : NaN;
}

function buildWsNoteUpdatePayload() {
    const titleEl = document.getElementById('noteTitle');
    const contentEl = document.getElementById('noteContent');
    const v = !noteVersionLoadPending ? getNoteContentVersion() : NaN;
    const payload = {
        type:      'update',
        note_id:   currentNoteIdForWS,
        title:     (titleEl && titleEl.value) || '',
        content:   (contentEl && contentEl.value) || '',
        user_name: currentUserName
    };
    if (Number.isFinite(v)) payload.version = v;
    return payload;
}

function setNoteOwnerToolbarVisible(visible) {
    const display = visible ? 'block' : 'none';
    ['toolsSection', 'colorSection', 'shareManagerSection', 'btnTrashNote'].forEach((id) => {
        const node = document.getElementById(id);
        if (node) node.style.display = display;
    });
}

// ====================== DOM READY (một handler duy nhất) ======================
document.addEventListener('DOMContentLoaded', () => {
    bulkShareModal = new bootstrap.Modal(document.getElementById('bulkShareModal'));
    // --- Modals Bootstrap ---
    noteModal          = new bootstrap.Modal(document.getElementById('noteModal'));
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    resetChoiceModal = new bootstrap.Modal(document.getElementById('resetChoiceModal'));
    customAlertModal   = new bootstrap.Modal(document.getElementById('customAlertModal'));
    customConfirmModal = new bootstrap.Modal(document.getElementById('customConfirmModal'));
    const passwordModalEl = document.getElementById('passwordModal');
    if (passwordModalEl) {
        passwordModalEl.addEventListener('hidden.bs.modal', resetPasswordModalToDefault);
    }
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

    // --- Theme preview (đã được thay bằng profile mới, giữ lại applyTheme) ---
    // Không còn themeSelect, fontSelect cũ vì đã chuyển vào profile

    // --- Force sync content khi blur khỏi noteContent ---
    const noteContentEl = document.getElementById('noteContent');
    if (noteContentEl) {
        noteContentEl.addEventListener('blur', function () {
            if (wsReady && currentNoteIdForWS) {
                _wsSendEditorStateIfChanged();
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
    // Khôi phục màu nền mặc định từ session nếu có
    const savedNoteColor = localStorage.getItem('noteapp_note_color') || document.documentElement.style.getPropertyValue('--note-default-color');
    if (savedNoteColor) {
        document.documentElement.style.setProperty('--note-default-color', savedNoteColor);
    }
    // Lưu lại khi người dùng thay đổi trong profile (sẽ được xử lý qua savePreferences)
        // Bulk selection buttons
    const toggleBtn = document.getElementById('toggleBulkModeBtn');
    if (toggleBtn) toggleBtn.addEventListener('click', toggleBulkMode);
    
    const bulkDelete = document.getElementById('bulkDeleteBtn');
    const bulkRestore = document.getElementById('bulkRestoreBtn');
    const bulkPermanent = document.getElementById('bulkPermanentBtn');
    const bulkShare = document.getElementById('bulkShareBtn');
    const bulkCancel = document.getElementById('bulkCancelBtn');
    
    if (bulkDelete) bulkDelete.addEventListener('click', () => bulkAction('trash'));
    if (bulkRestore) bulkRestore.addEventListener('click', () => bulkAction('restore'));
    if (bulkPermanent) bulkPermanent.addEventListener('click', () => bulkAction('permanent'));
    if (bulkShare) bulkShare.addEventListener('click', () => bulkAction('share'));
    if (bulkCancel) bulkCancel.addEventListener('click', toggleBulkMode);
});

// ====================== REALTIME TYPING BROADCAST (80ms debounce) ======================
let realtimeTypingTimer = null;
document.addEventListener('input', function (e) {
    if (window.__remoteUpdating) return;
    if (!wsReady || !currentNoteIdForWS) return;
    if (e.target.id !== 'noteTitle' && e.target.id !== 'noteContent') return;

    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = setTimeout(() => {
        _wsSendEditorStateIfChanged();
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
        if (bulkMode) toggleBulkMode();
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

function scheduleLiveSearchAfterSave() {
    clearTimeout(liveSearchAfterSaveTimer);
    liveSearchAfterSaveTimer = setTimeout(() => {
        liveSearchAfterSaveTimer = null;
        liveSearch();
    }, 500);
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

        let offlineSyncBadge = '';
        if (n.syncStatus === 'error' || n.lastSyncError) {
            offlineSyncBadge = `<span class="badge bg-danger ms-1" title="${escapeHtml(n.lastSyncError || 'Lỗi đồng bộ')}">Chưa đồng bộ</span>`;
        } else if (n.syncStatus === 'pending' && n.nextRetryAt && n.nextRetryAt > Date.now()) {
            offlineSyncBadge = '<span class="badge bg-secondary ms-1" title="Đang chờ thử lại">Chờ thử lại</span>';
        } else if (n.syncStatus === 'pending' || isNoteTemp(n.id)) {
            offlineSyncBadge = '<span class="badge bg-warning text-dark ms-1">Chờ đồng bộ</span>';
        }

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
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'bulk-checkbox';
        checkbox.dataset.id = n.id;
        checkbox.disabled = (n.is_locked == 1); // nếu có mật khẩu
        checkbox.addEventListener('change', (e) => {
            e.stopPropagation();
            if (checkbox.checked) selectedNotes.add(n.id);
            else selectedNotes.delete(n.id);
            updateBulkToolbar();
        });
        card.appendChild(checkbox);
        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                     onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                     <i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1 flex-wrap">
                ${icons} ${escapeHtml(n.title) || 'Không tiêu đề'} ${offlineSyncBadge}
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
// ====================== BULK SELECTION ======================
function updateBulkToolbar() {
    const count = selectedNotes.size;
    const counter = document.getElementById('bulkCounter');
    if (counter) counter.innerText = count;
    
    const isTrash = (currentViewMode === 'trash');
    const deleteBtn = document.getElementById('bulkDeleteBtn');
    const restoreBtn = document.getElementById('bulkRestoreBtn');
    const permanentBtn = document.getElementById('bulkPermanentBtn');
    const shareBtn = document.getElementById('bulkShareBtn');
    
    // Xóa class d-none và thêm d-inline-flex (hoặc d-flex) cho các nút cần hiển thị
    if (deleteBtn) {
        if (isTrash) {
            deleteBtn.classList.add('d-none');
        } else {
            deleteBtn.classList.remove('d-none');
            deleteBtn.classList.add('d-inline-flex');
        }
    }
    if (restoreBtn) {
        if (isTrash) {
            restoreBtn.classList.remove('d-none');
            restoreBtn.classList.add('d-inline-flex');
        } else {
            restoreBtn.classList.add('d-none');
        }
    }
    if (permanentBtn) {
        if (isTrash) {
            permanentBtn.classList.remove('d-none');
            permanentBtn.classList.add('d-inline-flex');
        } else {
            permanentBtn.classList.add('d-none');
        }
    }
    if (shareBtn) {
        if (currentViewMode === 'my_notes' && !isTrash) {
            shareBtn.classList.remove('d-none');
            shareBtn.classList.add('d-inline-flex');
        } else {
            shareBtn.classList.add('d-none');
        }
    }
}

function toggleBulkMode() {
    bulkMode = !bulkMode;
    const container = document.getElementById('notesContainer');
    const toolbar = document.getElementById('bulkToolbar');
    const toggleBtn = document.getElementById('toggleBulkModeBtn');
    
    if (bulkMode) {
        container.classList.add('bulk-mode');
        // Thay vì remove d-none, thêm class visible để toolbar trượt lên
        toolbar.classList.remove('bulk-toolbar-visible');
        // Force reflow để transition hoạt động
        void toolbar.offsetWidth;
        toolbar.classList.add('bulk-toolbar-visible');
        toggleBtn.classList.add('active');
        selectedNotes.clear();
        updateBulkToolbar();  // Cập nhật trạng thái các nút dựa trên view hiện tại
    } else {
        container.classList.remove('bulk-mode');
        // Ẩn toolbar bằng class thay vì d-none
        toolbar.classList.remove('bulk-toolbar-visible');
        toggleBtn.classList.remove('active');
        // Bỏ chọn tất cả checkbox
        document.querySelectorAll('.bulk-checkbox').forEach(cb => {
            cb.checked = false;
        });
        selectedNotes.clear();
    }
}

async function bulkAction(action) {
    if (selectedNotes.size === 0) {
        showToast('Chưa chọn ghi chú nào', 'warning');
        return;
    }
    
    if (action === 'share') {
        // Hiển thị modal nhập email và quyền
        document.getElementById('bulkShareEmails').value = '';
        document.getElementById('bulkSharePermission').value = 'read';
        bulkShareModal.show();
        
        // Xử lý khi nhấn nút xác nhận
        const confirmBtn = document.getElementById('bulkShareConfirmBtn');
        const oldHandler = confirmBtn.onclick;
        confirmBtn.onclick = async () => {
            const emails = document.getElementById('bulkShareEmails').value.trim();
            const permission = document.getElementById('bulkSharePermission').value;
            if (!emails) {
                showToast('Vui lòng nhập email', 'warning');
                return;
            }
            bulkShareModal.hide();
            await executeBulkShare(emails, permission);
        };
        return;
    }
    
    // Các action khác (trash, restore, permanent) dùng confirm cũ
    let confirmMsg = '';
    if (action === 'trash') {
        confirmMsg = `Bạn có chắc muốn chuyển ${selectedNotes.size} ghi chú vào thùng rác?`;
    } else if (action === 'restore') {
        confirmMsg = `Khôi phục ${selectedNotes.size} ghi chú?`;
    } else if (action === 'permanent') {
        confirmMsg = `Xóa vĩnh viễn ${selectedNotes.size} ghi chú? Hành động này không thể hoàn tác.`;
    } else {
        return;
    }
    
    showConfirm(confirmMsg, () => executeBulkAction(action));
}

async function executeBulkShare(emails, permission) {
    const fd = new FormData();
    fd.append('action', 'share');
    fd.append('ids', Array.from(selectedNotes).join(','));
    fd.append('emails', emails);
    fd.append('permission', permission);
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/bulk_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            toggleBulkMode();
            liveSearch();
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}

async function executeBulkAction(action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('ids', Array.from(selectedNotes).join(','));
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/bulk_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            toggleBulkMode();
            liveSearch();
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}
// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    // Kiểm tra modal tồn tại
    const noteIdInput = document.getElementById('noteId');
    if (!noteIdInput) {
        console.error('Modal chưa sẵn sàng. Reload trang...');
        location.reload();
        return;
    }

    currentNoteId = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        // Reset modal password về trạng thái mặc định TRƯỚC KHI SHOW
        resetPasswordModalToDefault();

        const modalTitle = document.getElementById('passwordModalTitle');
        if (modalTitle) modalTitle.textContent = '🔒 Ghi chú đã bị khóa';

        const pwdInput = document.getElementById('notePasswordInput');
        if (pwdInput) pwdInput.value = '';

        const errorEl = document.getElementById('passwordError');
        if (errorEl) errorEl.style.display = 'none';

        window.tempOpenData = { id, title, content, color, permission, ownerName };
        passwordModalInstance.show();

        setTimeout(() => {
            const input = document.getElementById('notePasswordInput');
            if (input) input.focus();
        }, 500);
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    let pwdInput = document.getElementById('notePasswordInput');
    if (!pwdInput) {
        console.error('[submitNotePassword] Input mật khẩu không tồn tại! Khôi phục modal...');
        resetPasswordModalToDefault();
        pwdInput = document.getElementById('notePasswordInput');
        if (!pwdInput) {
            showAlert('Lỗi giao diện, vui lòng tải lại trang.', 'danger');
            return;
        }
    }

    const password = pwdInput.value.trim();
    const errorEl = document.getElementById('passwordError');
    if (!password) {
        if (errorEl) {
            errorEl.textContent = 'Vui lòng nhập mật khẩu!';
            errorEl.style.display = 'block';
        }
        pwdInput.focus();
        return;
    }

    const fd = new FormData();
    fd.append('note_id', currentNoteId);
    fd.append('password', password);
    appendCsrfToken(fd);

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
                if (errorEl) {
                    errorEl.textContent = d.message || 'Mật khẩu không đúng!';
                    errorEl.style.display = 'block';
                }
                pwdInput.value = '';
                pwdInput.focus();
            }
        })
        .catch(() => {
            if (errorEl) {
                errorEl.textContent = 'Lỗi kết nối!';
                errorEl.style.display = 'block';
            }
        });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    // Reset biến đếm retry khi mở một note mới
    window._conflictRetryCount = 0;

    currentPermission = permission;
    currentNoteId     = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl = document.getElementById('noteContent');
    delete contentEl.dataset.version;
    // Đặt version tạm thời là '0' (sẽ được cập nhật sau khi fetch từ server)
    contentEl.dataset.version = '0';

    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText            = '';
    lastAutoSavePersistSig = '';
    noteConflictResolutionLock = false;
    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    clearTimeout(liveSearchAfterSaveTimer);
    clearTimeout(realtimeTypingTimer);
    liveSearchAfterSaveTimer = null;
    autoSaveTimer          = null;
    autoSaveRetryTimer     = null;
    autoSaveBusyTimer      = null;
    realtimeTypingTimer    = null;
    autoSaveInFlightSeq++;

    if (id) {
        noteVersionLoadPending = true;
        const fetchGen = ++noteVersionFetchGen;
        fetch(`api/get_notes.php?note_id=${id}`)
            .then(r => r.json())
            .then(note => {
                if (note != null && note.version != null && note.version !== '') {
                    contentEl.dataset.version = String(note.version);
                }
            })
            .catch(() => { /* giữ nguyên version '0' nếu fetch lỗi */ })
            .finally(() => {
                if (fetchGen === noteVersionFetchGen) {
                    noteVersionLoadPending = false;
                }
            });
    } else {
        noteVersionFetchGen++;
        noteVersionLoadPending = false;
    }

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
            setNoteOwnerToolbarVisible(true);

            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(r => r.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
            if (wsBadge) wsBadge.style.display = 'inline-flex';
        }
    }

    if (!id) {
        document.getElementById('noteTitle').placeholder   = 'Nhập tiêu đề ghi chú...';
        document.getElementById('noteContent').placeholder = 'Nhập nội dung ghi chú của bạn...';
    }

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
    clearTimeout(liveSearchAfterSaveTimer);
    liveSearchAfterSaveTimer = null;
    stopRealtime();
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
function _autoSavePayloadSignature() {
    const noteId  = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;
    return `${noteId}\x1e${title}\x1e${content}\x1e${getNoteContentVersion()}`;
}

function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    if (window.__remoteUpdating) return;
    if (noteConflictResolutionLock) return;

    const noteId  = document.getElementById('noteId').value;
    if (noteId && noteVersionLoadPending) return;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;

    if (!noteId && !title && !content) return;

    const hadPendingDebounce = !!autoSaveTimer;
    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    autoSaveRetryTimer = null;
    autoSaveBusyTimer  = null;

    if (!hadPendingDebounce) {
        document.getElementById('saveStatus').innerHTML = '<i class="bi bi-hourglass-split"></i> Đang lưu...';
    }

    autoSaveTimer = setTimeout(() => {
        autoSaveTimer = null;

        const runCommitted = () => {
            if (isSaving) {
                clearTimeout(autoSaveBusyTimer);
                autoSaveBusyTimer = setTimeout(runCommitted, 150);
                return;
            }
            autoSaveBusyTimer = null;

            const nid  = document.getElementById('noteId').value;
            const tit  = document.getElementById('noteTitle').value.trim();
            const cont = document.getElementById('noteContent').value;

            if (!nid && !tit && !cont) {
                document.getElementById('saveStatus').innerText = '';
                return;
            }

            if (nid && noteVersionLoadPending) return;

            if (nid && _autoSavePayloadSignature() === lastAutoSavePersistSig) {
                const statusEl = document.getElementById('saveStatus');
                statusEl.innerHTML = lastAutoSavePersistSig
                    ? '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu'
                    : '';
                return;
            }

            if (!navigator.onLine) {
                const vOff = getNoteContentVersion();
                const noteData = {
                    id:         nid || 'temp_' + Date.now(),
                    title:      tit,
                    content:    cont,
                    version:    Number.isFinite(vOff) ? vOff : 1,
                    updated_at: new Date().toISOString()
                };
                saveNoteOffline(noteData);
                document.getElementById('saveStatus').innerHTML =
                    '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Đã lưu offline</span>';
                showToast('Không có mạng. Ghi chú đã lưu cục bộ.', 'warning');
                return;
            }

            const verNum = getNoteContentVersion();
            if (nid && !Number.isFinite(verNum)) {
                document.getElementById('saveStatus').innerText = '';
                return;
            }

            isSaving = true;
            const mySeq = ++autoSaveInFlightSeq;

            const fd = new FormData();
            fd.append('id',      nid);
            fd.append('title',   tit);
            fd.append('content', cont);
            fd.append('version', nid ? String(verNum) : '1');
            appendCsrfToken(fd);

            fetch('api/save_note.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(d => {
                    if (mySeq !== autoSaveInFlightSeq) return;

                    const statusEl  = document.getElementById('saveStatus');
                    const contentEl = document.getElementById('noteContent');

                    if (!d.success && d.conflict) {
                        clearTimeout(autoSaveRetryTimer);
                        autoSaveRetryTimer = null;

                        const serverVersion = d.version;
                        if (!window._conflictRetryCount) window._conflictRetryCount = 0;
                        if (window._conflictRetryCount >= 2) {
                            window._conflictRetryCount = 0;
                            statusEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Xung đột, vui lòng thử lại';
                            showToast('Không thể đồng bộ do xung đột liên tục, vui lòng tải lại trang.', 'danger');
                            return;
                        }

                        if (serverVersion !== undefined && serverVersion !== null && serverVersion !== '') {
                            contentEl.dataset.version = String(serverVersion);
                            window._conflictRetryCount++;
                        }

                        statusEl.innerHTML = '<i class="bi bi-arrow-repeat text-info"></i> Đang đồng bộ lại...';
                        autoSaveRetryTimer = setTimeout(() => {
                            autoSaveRetryTimer = null;
                            autoSave();
                        }, 300);
                        return;
                    }

                    // Lưu thành công -> reset retry counter
                    window._conflictRetryCount = 0;

                    if (d.success) {
                        statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu';

                        if (!nid && d.note_id) {
                            document.getElementById('noteId').value = d.note_id;
                            currentNoteId = d.note_id;
                            setNoteOwnerToolbarVisible(true);
                        }

                        if (d.version) {
                            contentEl.dataset.version = d.version;
                        }

                        lastAutoSavePersistSig = _autoSavePayloadSignature();
                        scheduleLiveSearchAfterSave();
                    } else {
                        statusEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Lỗi lưu';
                        if (d.message) showToast(d.message, 'danger');
                    }
                })
                .catch(() => {
                    if (mySeq !== autoSaveInFlightSeq) return;
                    document.getElementById('saveStatus').innerHTML =
                        '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Lưu offline</span>';
                    const noteData = {
                        id:         nid || 'temp_' + Date.now(),
                        title:      tit,
                        content:    cont,
                        version:    Number.isFinite(getNoteContentVersion()) ? getNoteContentVersion() : 1,
                        updated_at: new Date().toISOString()
                    };
                    saveNoteOffline(noteData);
                    showToast('Không kết nối mạng. Ghi chú đã được lưu cục bộ.', 'warning');
                })
                .finally(() => {
                    if (mySeq === autoSaveInFlightSeq) {
                        isSaving = false;
                    }
                    if (mySeq !== autoSaveInFlightSeq) return;
                    setTimeout(() => {
                        if (isSaving || autoSaveTimer) return;
                        if (currentViewMode === 'trash' || currentPermission === 'read') return;
                        if (noteVersionLoadPending) return;
                        if (noteConflictResolutionLock) return;
                        if (!navigator.onLine) return;
                        if (_autoSavePayloadSignature() !== lastAutoSavePersistSig) {
                            autoSave();
                        }
                    }, 0);
                });
        };
        runCommitted();
    }, 1000);
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
    appendCsrfToken(fd);

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
        appendCsrfToken(fd);
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
    // Kiểm tra xem note đã có mật khẩu chưa? Lấy từ dataset hoặc gọi API? 
    // Hiện tại dùng biến isLockedState (được set khi mở note có password_hash)
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
        onConfirm: (vals, showError) => {
            const [pw, pw2] = vals;
            if (pw.length < 4) {
                showError('Mật khẩu phải có ít nhất 4 ký tự!');
                return false;
            }
            if (pw !== pw2) {
                showError('Mật khẩu xác nhận không khớp!');
                return false;
            }
            const fd = new FormData();
            fd.append('note_id',          id);
            fd.append('action',           'lock');
            fd.append('password',         pw);
            fd.append('confirm_password', pw2);
            appendCsrfToken(fd);
            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        isLockedState = true;
                        document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
                        liveSearch();
                        return true;
                    } else {
                        showError(d.message || 'Không thể đặt khóa!');
                        return false;
                    }
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
        onConfirm: (vals, showError, actionValue) => {
            const oldPw = vals[0];
            if (!oldPw) {
                showError('Vui lòng nhập mật khẩu hiện tại!');
                return false;
            }
            // Gửi request verify mật khẩu cũ
            const fd = new FormData();
            fd.append('note_id',      id);
            fd.append('old_password', oldPw);
            fd.append('action',       'verify');
            appendCsrfToken(fd);
            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        showError(d.message || 'Mật khẩu không đúng!');
                        return false;
                    }
                    // Xác thực thành công
                    if (actionValue === 'unlock') {
                        // Gửi yêu cầu mở khóa
                        const fdUnlock = new FormData();
                        fdUnlock.append('note_id',      id);
                        fdUnlock.append('old_password', oldPw);
                        fdUnlock.append('action',       'unlock');
                        appendCsrfToken(fdUnlock);
                        return fetch('api/lock_note.php', { method: 'POST', body: fdUnlock })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    isLockedState = false;
                                    document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
                                    liveSearch();
                                    return true;
                                } else {
                                    showError(res.message || 'Không thể mở khóa!');
                                    return false;
                                }
                            });
                    } else if (actionValue === 'change') {
                        // Chuyển modal hiện tại sang chế độ đổi mật khẩu (không đóng modal)
                        _switchToChangePasswordMode(id, oldPw);
                        return false; // Không đóng modal tự động
                    }
                    return false;
                });
        }
    });
}

// function _showChangePasswordModal(id, oldPw) {
//     // Kiểm tra oldPw có hợp lệ không
//     if (!oldPw) {
//         showToast('Lỗi: Không tìm thấy mật khẩu cũ!', 'danger');
//         return;
//     }
//     _openPasswordModal({
//         title: '🔑 Đặt mật khẩu mới',
//         fields: [
//             { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
//             { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
//         ],
//         onConfirm: (vals, showError) => {
//             const [newPw, confirmPw] = vals;
//             if (newPw.length < 4) {
//                 showError('Mật khẩu phải có ít nhất 4 ký tự!');
//                 return false;
//             }
//             if (newPw !== confirmPw) {
//                 showError('Mật khẩu xác nhận không khớp!');
//                 return false;
//             }
//             const fd = new FormData();
//             fd.append('note_id',          id);
//             fd.append('action',           'change');
//             fd.append('old_password',     oldPw);  // Dùng oldPw từ tham số
//             fd.append('password',         newPw);
//             fd.append('confirm_password', confirmPw);
//             appendCsrfToken(fd);
//             return fetch('api/lock_note.php', { method: 'POST', body: fd })
//                 .then(r => r.json())
//                 .then(d => {
//                     if (d.success) {
//                         showToast('Đổi mật khẩu thành công!', 'success');
//                         liveSearch();
//                         return true;
//                     } else {
//                         showError(d.message || 'Không thể đổi mật khẩu!');
//                         return false;
//                     }
//                 });
//         }
//     });
// }
function _switchToChangePasswordMode(id, oldPw) {
    // Lấy các phần tử của modal hiện tại
    const titleEl = document.getElementById('passwordModalTitle');
    const bodyEl = document.getElementById('passwordModal').querySelector('.modal-body');
    const footerEl = document.getElementById('passwordModal').querySelector('.modal-footer');
    
    // Thay đổi tiêu đề và nội dung modal thành giao diện đổi mật khẩu
    titleEl.textContent = '🔑 Đặt mật khẩu mới';
    bodyEl.innerHTML = `
        <input type="password" id="pm_new_pw" class="form-control mb-2" placeholder="Mật khẩu mới (≥ 4 ký tự)" autocomplete="off">
        <input type="password" id="pm_confirm_pw" class="form-control mb-2" placeholder="Nhập lại mật khẩu" autocomplete="off">
        <div id="pm_error" class="text-danger small mt-1" style="display:none;"></div>
    `;
    
    // Xóa các nút cũ và thêm nút xác nhận mới
    footerEl.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" id="changePasswordConfirmBtn" class="btn btn-primary">Xác nhận</button>
    `;
    
    const errorEl = document.getElementById('pm_error');
    const confirmBtn = document.getElementById('changePasswordConfirmBtn');
    
    confirmBtn.onclick = () => {
        const newPw = document.getElementById('pm_new_pw').value.trim();
        const confirmPw = document.getElementById('pm_confirm_pw').value.trim();
        
        if (newPw.length < 4) {
            errorEl.textContent = 'Mật khẩu phải có ít nhất 4 ký tự!';
            errorEl.style.display = 'block';
            return;
        }
        if (newPw !== confirmPw) {
            errorEl.textContent = 'Mật khẩu xác nhận không khớp!';
            errorEl.style.display = 'block';
            return;
        }
        
        errorEl.style.display = 'none';
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Đang xử lý...';
        
        const fd = new FormData();
        fd.append('note_id',          id);
        fd.append('action',           'change');
        fd.append('old_password',     oldPw);
        fd.append('password',         newPw);
        fd.append('confirm_password', confirmPw);
        appendCsrfToken(fd);
        
        fetch('api/lock_note.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showToast('Đổi mật khẩu thành công!', 'success');
                    passwordModalInstance.hide();
                    liveSearch();
                } else {
                    errorEl.textContent = d.message || 'Không thể đổi mật khẩu!';
                    errorEl.style.display = 'block';
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Xác nhận';
                }
            })
            .catch(() => {
                errorEl.textContent = 'Lỗi kết nối, vui lòng thử lại!';
                errorEl.style.display = 'block';
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Xác nhận';
            });
    };
}
function _openPasswordModal(config) {
    const titleEl = document.getElementById('passwordModalTitle');
    const bodyEl = document.getElementById('passwordModal').querySelector('.modal-body');
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
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        footerEl.querySelectorAll('.pm-action-btn').forEach(b => {
            b.disabled = false;
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
                .then(shouldClose => {
                    if (shouldClose === true) {
                        passwordModalInstance.hide();
                        resetPasswordModalToDefault();
                        liveSearch();
                    }
                })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    const modalEl = document.getElementById('passwordModal');
    const onHidden = () => {
        resetPasswordModalToDefault();
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    passwordModalInstance.show();

    setTimeout(() => {
        const firstField = document.getElementById(config.fields[0]?.id);
        if (firstField) firstField.focus();
    }, 100);
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
    appendCsrfToken(fd);

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
        appendCsrfToken(fd);

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
    appendCsrfToken(fd);
    fetch('api/restore_note.php', { method: 'POST', body: fd })
        .then(() => { showAlert('Khôi phục thành công!', 'success'); closeAndReload(); });
}

// ====================== WEBSOCKET REALTIME ======================
let ws                    = null;
let wsReconnectTimer      = null;
let wsAwaitCloseReconnect = false;
let wsReady               = false;
let currentNoteIdForWS    = null;
let _pollInterval         = null;
let _lastWsOutboundSig    = '';
let _lastWsInboundKey     = '';

const WS_HOST = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':8080';

function connectWebSocket() {
    if (ws) {
        if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
            return;
        }
        if (ws.readyState === WebSocket.CLOSING) {
            if (!wsAwaitCloseReconnect) {
                wsAwaitCloseReconnect = true;
                ws.onopen    = null;
                ws.onmessage = null;
                ws.onclose   = null;
                ws.onerror   = null;
                ws.addEventListener('close', () => {
                    wsAwaitCloseReconnect = false;
                    ws = null;
                    connectWebSocket();
                }, { once: true });
            }
            return;
        }
        ws = null;
    }

    clearTimeout(wsReconnectTimer);
    wsReconnectTimer = null;

    if (!currentUserId) {
        console.warn('WebSocket: chưa đăng nhập, dùng fallback polling.');
        _startFallbackPolling();
        return;
    }

    fetch('api/ws_token.php', { credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : Promise.reject()))
        .then((d) => {
            if (!d.success || !d.token) return Promise.reject(new Error('ws_token'));
            return d.token;
        })
        .then((token) => {
            let socket;
            try {
                ws = new WebSocket(WS_HOST);
                socket = ws;
            } catch (e) {
                console.warn('WebSocket không khả dụng, dùng fallback polling.');
                _startFallbackPolling();
                return;
            }

            socket.onopen = () => {
                if (ws !== socket) return;
                clearTimeout(wsReconnectTimer);
                wsReconnectTimer = null;
                _stopFallbackPolling();
                socket.send(JSON.stringify({ type: 'auth', token }));
                _setWsStatus('connecting');
            };

            socket.onmessage = (event) => {
    if (ws !== socket) return;
    try {
        const data = JSON.parse(event.data);

        if (data.type === 'auth_error') {
            wsReady = false;
            _setWsStatus('offline');
            try { socket.close(); } catch (e2) {}
            return;
        }

        if (data.type === 'join_denied' && data.note_id == currentNoteIdForWS) {
            console.warn('[WS] join_denied:', data.message || '');
            return;
        }

        if (data.type === 'auth_success') {
            wsReady = true;
            _setWsStatus('online');
            if (currentNoteIdForWS) _wsSend({ type: 'join_note', note_id: currentNoteIdForWS });
        }

        // ================== XỬ LÝ CẬP NHẬT NỘI DUNG (ĐÃ SỬA) ==================
        if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
            if (data.user_name === currentUserName) return;

            const contentEl = document.getElementById('noteContent');
            const incomingVer = data.version != null && data.version !== '' ? parseInt(data.version, 10) : NaN;
            const rawLocal = contentEl && contentEl.dataset.version;
            const localVer = rawLocal !== undefined && rawLocal !== '' ? parseInt(rawLocal, 10) : NaN;

            // Chỉ cập nhật version nếu version mới lớn hơn version hiện tại (tránh ghi đè bằng version cũ)
            if (Number.isFinite(incomingVer) && (!Number.isFinite(localVer) || incomingVer > localVer)) {
                contentEl.dataset.version = String(incomingVer);
            }

            const titleEl   = document.getElementById('noteTitle');
            const isEditingTitle   = document.activeElement === titleEl;
            const isEditingContent = document.activeElement === contentEl;

            window.__remoteUpdating = true;
            try {
                if (data.title !== undefined && !isEditingTitle) {
                    titleEl.value = data.title;
                }
                if (data.content !== undefined) {
                    const currentContent  = contentEl.value    || '';
                    const incomingContent = String(data.content);
                    if (!isEditingContent) {
                        contentEl.value = incomingContent;
                    } else {
                        const isDeleting   = incomingContent.length < currentContent.length;
                        const tooDifferent = Math.abs(incomingContent.length - currentContent.length) > 5;
                        if (isDeleting || tooDifferent) {
                            const cursorPos = contentEl.selectionStart;
                            contentEl.value = incomingContent;
                            try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
                        }
                    }
                }
            } finally {
                window.__remoteUpdating = false;
            }
            _showTypingIndicator(data.user_name);
        }

        // ========== GIỮ NGUYÊN CÁC TYPE KHÁC ==========
        if (data.type === 'color_update' && data.note_id == currentNoteIdForWS) {
            const modalWrapper = document.getElementById('modalContentWrapper');
            if (modalWrapper) {
                modalWrapper.style.backgroundColor = data.color;
                modalWrapper.style.setProperty('--note-individual-color', data.color);
            }
            const cards = document.querySelectorAll('.note-card');
            cards.forEach(card => {
                const body = card.querySelector('.card-body');
                if (body && body.dataset.id == data.note_id) {
                    card.style.backgroundColor = data.color;
                }
            });
            _showTypingIndicator(data.user_name + ' đã đổi màu');
        }

        if (data.type === 'image_added' && data.note_id == currentNoteIdForWS) {
            renderImage(data.file_path, data.image_id, currentPermission);
            _showTypingIndicator(data.user_name + ' đã thêm ảnh');
        }

        if (data.type === 'image_deleted' && data.note_id == currentNoteIdForWS) {
            const imgDiv = document.querySelector(`#imagePreviewContainer [data-image-id="${data.image_id}"]`);
            if (imgDiv) imgDiv.remove();
            _showTypingIndicator(data.user_name + ' đã xóa ảnh');
        }

        if (data.type === 'presence' && data.note_id == currentNoteIdForWS) {
            _renderPresence(data.users);
        }
    } catch (e) {
        console.error('WS parse error:', e);
    }
};

            socket.onclose = () => {
                if (ws !== socket) return;
                wsReady = false;
                _setWsStatus('offline');
                ws = null;
                clearTimeout(wsReconnectTimer);
                wsReconnectTimer = setTimeout(() => {
                    wsReconnectTimer = null;
                    connectWebSocket();
                }, 3000);
            };

            socket.onerror = () => {
                if (ws !== socket) return;
                _setWsStatus('offline');
            };
        })
        .catch(() => {
            console.warn('WebSocket không lấy được token, dùng fallback polling.');
            _startFallbackPolling();
        });
}

function _wsSend(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj));
}

function _wsSendEditorStateIfChanged() {
    const payload = buildWsNoteUpdatePayload();
    const sig = `${payload.note_id}\x1e${payload.title}\x1e${payload.content}\x1e${payload.version}`;
    if (sig === _lastWsOutboundSig) return;
    _lastWsOutboundSig = sig;
    _wsSend(payload);
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
                window.__remoteUpdating = true;
                try {
                    if (document.activeElement !== titleEl)   titleEl.value   = note.title   ?? '';
                    if (document.activeElement !== contentEl) contentEl.value = note.content ?? '';
                    if (note.version) contentEl.dataset.version = note.version;
                } finally {
                    window.__remoteUpdating = false;
                }
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
    if (currentNoteIdForWS !== noteId) {
        if (currentNoteIdForWS && wsReady) {
            _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
        }
        _lastWsOutboundSig = '';
        _lastWsInboundKey  = '';
    }
    currentNoteIdForWS = noteId;
    connectWebSocket();
    if (wsReady) _wsSend({ type: 'join_note', note_id: noteId });
}

function stopRealtime() {
    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = null;
    if (currentNoteIdForWS) _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
    currentNoteIdForWS = null;
    _lastWsOutboundSig = '';
    _lastWsInboundKey  = '';
    _stopFallbackPolling();
    const p = document.getElementById('wsPresenceBar');
    const t = document.getElementById('wsTypingIndicator');
    if (p) p.textContent   = '';
    if (t) { t.textContent = ''; t.style.display = 'none'; }
}

// ====================== ẢNH ======================
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f = document.getElementById('imageInput').files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    appendCsrfToken(fd);

    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
                if (wsReady && currentNoteIdForWS == nid) {
                    _wsSend({
                        type: 'image_added',
                        note_id: nid,
                        image_id: d.image_id,
                        file_path: d.file_path,
                        user_name: currentUserName
                    });
                }
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
        `<div class="position-relative shadow-sm rounded" data-image-id="${id}">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;" data-id="${id}">${del}
         </div>`;
}

function deleteImage(id, btn) {
    showConfirm('Xóa ảnh này?', () => {
        fetch('api/delete_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const imgDiv = btn.closest('[data-image-id]');
                if (imgDiv) imgDiv.remove();
                if (wsReady && currentNoteIdForWS) {
                    _wsSend({
                        type: 'image_deleted',
                        note_id: currentNoteIdForWS,
                        image_id: id,
                        user_name: currentUserName
                    });
                }
            }
        });
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
        body:    appendCsrfUrlEncoded(`name=${encodeURIComponent(name)}`)
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function renameLabel(id, currentName) {
    renameLabelId = id;
    renameLabelCurrentName = currentName;
    
    const inputEl = document.getElementById('renameLabelInput');
    const errorEl = document.getElementById('renameLabelError');
    if (inputEl) inputEl.value = currentName;
    if (errorEl) errorEl.style.display = 'none';
    
    const modalEl = document.getElementById('renameLabelModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // Focus vào input sau khi modal hiển thị
    setTimeout(() => {
        if (inputEl) {
            inputEl.focus();
            inputEl.select();
        }
    }, 300);
    
    // Gắn sự kiện cho nút xác nhận (loại bỏ listener cũ để tránh trùng lặp)
    const confirmBtn = document.getElementById('renameLabelConfirmBtn');
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    newConfirmBtn.onclick = function() {
        const newName = inputEl.value.trim();
        if (!newName) {
            if (errorEl) {
                errorEl.textContent = 'Tên nhãn không được để trống!';
                errorEl.style.display = 'block';
            }
            return;
        }
        if (newName === renameLabelCurrentName) {
            modal.hide();
            return;
        }
        
        // Gửi request đổi tên
        fetch('api/manage_labels.php?action=rename', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: appendCsrfUrlEncoded(`id=${renameLabelId}&name=${encodeURIComponent(newName)}`)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                modal.hide();
                loadFilterLabels(() => liveSearch());
                showToast('Đã đổi tên nhãn thành công', 'success');
            } else {
                if (errorEl) {
                    errorEl.textContent = data.message || 'Lỗi khi đổi tên nhãn!';
                    errorEl.style.display = 'block';
                }
            }
        })
        .catch(() => {
            if (errorEl) {
                errorEl.textContent = 'Lỗi kết nối!';
                errorEl.style.display = 'block';
            }
        });
    };
}

function deleteLabel(id) {
    showConfirm('Xóa nhãn này?', () => {
        fetch('api/manage_labels.php?action=delete', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    appendCsrfUrlEncoded(`id=${id}`)
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
        body:    appendCsrfUrlEncoded(`note_id=${nid}&label_id=${lid}&action=add`)
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
        body:    appendCsrfUrlEncoded(`note_id=${nid}&label_id=${lid}&action=remove`)
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== PROFILE / SETTINGS (MỚI) ======================
function showProfileModal() {
    profileSubScreen = 'main';
    renderProfileScreen();
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
}

function renderProfileScreen() {
    const titleEl = document.getElementById('profileModalTitle');
    const bodyEl = document.getElementById('profileModalBody');
    const cfg = window.APP_CONFIG || {};

    if (profileSubScreen === 'main') {
        titleEl.innerHTML = '<i class="bi bi-person-circle"></i> Tài khoản & Tùy chỉnh';
        bodyEl.innerHTML = `
            <div class="d-grid gap-3">
                <button class="btn btn-outline-primary btn-lg py-3" onclick="profileGoTo('account')">
                    <i class="bi bi-gear-wide-connected fs-4 me-2"></i> Cài đặt tài khoản
                </button>
                <button class="btn btn-outline-secondary btn-lg py-3" onclick="profileGoTo('preferences')">
                    <i class="bi bi-palette fs-4 me-2"></i> Tùy chỉnh ghi chú
                </button>
            </div>
            <div class="mt-4 text-center">
                <img src="${escapeHtml(cfg.avatar || 'uploads/avatars/default-avatar.png')}" 
                     class="rounded-circle border shadow-sm" style="width:64px;height:64px;object-fit:cover;">
                <div class="mt-2">
                    <strong>${escapeHtml(cfg.displayName || 'Người dùng')}</strong><br>
                    <small class="text-muted">${escapeHtml(cfg.email || '')}</small>
                </div>
            </div>
        `;
    } 
    else if (profileSubScreen === 'account') {
        titleEl.innerHTML = '<i class="bi bi-arrow-left me-2" style="cursor:pointer;" onclick="profileGoTo(\'main\')"></i> Cài đặt tài khoản';
        bodyEl.innerHTML = `
            <form id="accountSettingsForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control" value="${escapeHtml(cfg.email || '')}" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên hiển thị</label>
                    <input type="text" id="displayNameInput" class="form-control" value="${escapeHtml(cfg.displayName || '')}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Đổi mật khẩu</label>
                    <input type="password" id="oldPassword" class="form-control mb-2" placeholder="Mật khẩu hiện tại">
                    <input type="password" id="newPassword" class="form-control mb-2" placeholder="Mật khẩu mới (≥6 ký tự)">
                    <input type="password" id="confirmPassword" class="form-control" placeholder="Xác nhận mật khẩu mới">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ảnh đại diện</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="previewAvatarAccount" src="${escapeHtml(cfg.avatar || 'uploads/avatars/default-avatar.png')}" 
                             style="width:60px;height:60px;object-fit:cover;border-radius:50%;">
                        <label class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-camera"></i> Chọn ảnh
                            <input type="file" id="avatarFileInput" hidden accept="image/*" onchange="previewAvatarAccount(this)">
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Quên mật khẩu?</span>
                        <button type="button" class="btn btn-link p-0" onclick="showForgotPasswordModal()">Gửi OTP / Link</button>
                    </div>
                </div>
                <button type="button" class="btn btn-success w-100" onclick="saveAccountSettings()">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
            </form>
        `;
    }
    else if (profileSubScreen === 'preferences') {
        titleEl.innerHTML = '<i class="bi bi-arrow-left me-2" style="cursor:pointer;" onclick="profileGoTo(\'main\')"></i> Tùy chỉnh ghi chú';
        const currentFontSize = cfg.fontSize || '16px';
        const currentTheme = cfg.theme || 'light';
        const currentNoteColor = cfg.noteColor || '#ffffff';
        const currentTextColor = cfg.textColor || '#0A1024';
        const currentFontFamily = cfg.fontFamily || 'Inter, system-ui, sans-serif';
        
        bodyEl.innerHTML = `
            <form id="preferencesForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Màu ghi chú mặc định</label>
                    <input type="color" id="prefNoteColor" class="form-control form-control-color" value="${currentNoteColor}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Màu chữ (ghi chú)</label>
                    <input type="color" id="prefTextColor" class="form-control form-control-color" value="${currentTextColor}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phông chữ</label>
                    <select id="prefFontFamily" class="form-select">
                        <option value="Inter, system-ui, sans-serif" ${currentFontFamily.includes('Inter') ? 'selected' : ''}>Inter (Mặc định)</option>
                        <option value="'Roboto', sans-serif" ${currentFontFamily.includes('Roboto') ? 'selected' : ''}>Roboto</option>
                        <option value="'Poppins', sans-serif" ${currentFontFamily.includes('Poppins') ? 'selected' : ''}>Poppins</option>
                        <option value="'Open Sans', sans-serif" ${currentFontFamily.includes('Open Sans') ? 'selected' : ''}>Open Sans</option>
                        <option value="'Lato', sans-serif" ${currentFontFamily.includes('Lato') ? 'selected' : ''}>Lato</option>
                        <option value="'Montserrat', sans-serif" ${currentFontFamily.includes('Montserrat') ? 'selected' : ''}>Montserrat</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Kích cỡ chữ</label>
                    <select id="prefFontSize" class="form-select">
                        <option value="14px" ${currentFontSize === '14px' ? 'selected' : ''}>Nhỏ (14px)</option>
                        <option value="16px" ${currentFontSize === '16px' ? 'selected' : ''}>Vừa (16px)</option>
                        <option value="18px" ${currentFontSize === '18px' ? 'selected' : ''}>Lớn (18px)</option>
                        <option value="20px" ${currentFontSize === '20px' ? 'selected' : ''}>Siêu lớn (20px)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Giao diện (Theme)</label>
                    <select id="prefTheme" class="form-select">
                        <option value="light" ${currentTheme === 'light' ? 'selected' : ''}>Sáng</option>
                        <option value="dark" ${currentTheme === 'dark' ? 'selected' : ''}>Tối</option>
                    </select>
                </div>
                <button type="button" class="btn btn-success w-100" onclick="savePreferences()">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
            </form>
        `;
    }
}

function profileGoTo(screen) {
    profileSubScreen = screen;
    renderProfileScreen();
}

function previewAvatarAccount(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatarAccount').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveAccountSettings() {
    const newDisplayName = document.getElementById('displayNameInput').value.trim();
    const avatarFile = document.getElementById('avatarFileInput').files[0];
    const oldPwd = document.getElementById('oldPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    
    // 1. Cập nhật tên hiển thị
    if (newDisplayName && newDisplayName !== window.APP_CONFIG?.displayName) {
        const fd = new FormData();
        fd.append('display_name', newDisplayName);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/update_display_name.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) showToast(data.message || 'Lỗi cập nhật tên', 'danger');
            else {
                window.APP_CONFIG.displayName = newDisplayName;
                showToast('Cập nhật tên thành công', 'success');
            }
        } catch(e) { showToast('Lỗi kết nối khi đổi tên', 'danger'); }
    }
    
    // 2. Đổi mật khẩu
    if (oldPwd || newPwd || confirmPwd) {
        if (!oldPwd || !newPwd || !confirmPwd) {
            showToast('Vui lòng nhập đầy đủ mật khẩu cũ và mới', 'warning');
            return;
        }
        if (newPwd.length < 6) {
            showToast('Mật khẩu mới phải có ít nhất 6 ký tự', 'warning');
            return;
        }
        if (newPwd !== confirmPwd) {
            showToast('Mật khẩu xác nhận không khớp', 'warning');
            return;
        }
        const fd = new FormData();
        fd.append('old_password', oldPwd);
        fd.append('new_password', newPwd);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/change_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) showToast(data.message, 'danger');
            else showToast('Đổi mật khẩu thành công', 'success');
        } catch(e) { showToast('Lỗi kết nối', 'danger'); }
    }
    
    // 3. Upload avatar
    if (avatarFile) {
        const fd = new FormData();
        fd.append('avatar', avatarFile);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/update_avatar.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.APP_CONFIG.avatar = data.avatar;
                document.querySelector('.nav-avatar').src = data.avatar + '?v=' + Date.now();
                showToast('Cập nhật avatar thành công', 'success');
            } else showToast(data.message, 'danger');
        } catch(e) { showToast('Lỗi upload ảnh', 'danger'); }
    }
    
    // Refresh lại modal để cập nhật thông tin
    setTimeout(() => renderProfileScreen(), 500);
}

async function savePreferences() {
    const noteColor = document.getElementById('prefNoteColor').value;
    const textColor = document.getElementById('prefTextColor').value;
    const fontFamily = document.getElementById('prefFontFamily').value;
    const fontSize = document.getElementById('prefFontSize').value;
    const theme = document.getElementById('prefTheme').value;
    
    const fd = new FormData();
    fd.append('note_color', noteColor);
    fd.append('text_color', textColor);
    fd.append('font_family', fontFamily);
    fd.append('font_size', fontSize);
    fd.append('theme_color', theme);
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/update_preferences.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Áp dụng màu nền mặc định
            document.documentElement.style.setProperty('--note-default-color', noteColor);
            // Áp dụng màu chữ
            document.documentElement.style.setProperty('--note-text-color', textColor);
            // Áp dụng font chữ
            document.body.style.fontFamily = fontFamily;
            // Áp dụng cỡ chữ - set vào html để tránh bị CSS ghi đè
            document.documentElement.style.fontSize = fontSize;
            document.body.style.fontSize = fontSize; // fallback
            // Áp dụng theme
            applyTheme(theme);
            
            // Cập nhật tất cả các note card hiện có để đồng bộ giao diện
            document.querySelectorAll('.note-card').forEach(card => {
                // Đảm bảo font-size được kế thừa từ html
                card.style.fontSize = '';
                // Cập nhật màu chữ nếu card chưa có màu nền riêng
                const titleEl = card.querySelector('.card-title');
                const textEl = card.querySelector('.card-text');
                if (titleEl && !card.style.backgroundColor) titleEl.style.color = textColor;
                if (textEl && !card.style.backgroundColor) textEl.style.color = textColor;
            });
            
            // Cập nhật modal nếu đang mở
            const modalContent = document.getElementById('modalContentWrapper');
            if (modalContent && getComputedStyle(modalContent).backgroundColor !== 'rgba(0, 0, 0, 0)') {
                modalContent.style.setProperty('--note-text-color', textColor);
                const modalTexts = modalContent.querySelectorAll('.modal-title, .modal-body, .form-label, p, span:not(.badge)');
                modalTexts.forEach(el => el.style.color = textColor);
            }
            
            // Cập nhật APP_CONFIG
            window.APP_CONFIG.noteColor = noteColor;
            window.APP_CONFIG.textColor = textColor;
            window.APP_CONFIG.fontFamily = fontFamily;
            window.APP_CONFIG.fontSize = fontSize;
            window.APP_CONFIG.theme = theme;
            
            showToast('Đã lưu tùy chỉnh ghi chú', 'success');
            profileGoTo('main');
        } else {
            showToast(data.message || 'Lỗi lưu cài đặt', 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}

function showForgotPasswordModal() {
    const email = window.APP_CONFIG?.email;
    if (!email) {
        showToast('Không tìm thấy email của bạn', 'danger');
        return;
    }
    // Chuyển hướng đến trang reset_password.php với email được điền sẵn
    window.location.href = `reset_password.php?email=${encodeURIComponent(email)}`;
}

async function sendResetRequest(type) {
    const email = window.APP_CONFIG?.email;
    if (!email) return;
    const fd = new FormData();
    fd.append('email', email);
    fd.append('type', type);
    appendCsrfToken(fd);
    try {
        const res = await fetch('api/send_reset_code.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(`Đã gửi ${type === 'otp' ? 'mã OTP' : 'link reset'} đến email của bạn`, 'success');
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) { showToast('Lỗi gửi yêu cầu', 'danger'); }
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
    const fd = new FormData();
    fd.append('id', id);
    fd.append('is_pinned', state);
    appendCsrfToken(fd);
    fetch('api/pin_note.php', { method: 'POST', body: fd })
        .then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) {
        showAlert('Vui lòng lưu ghi chú trước khi đổi màu!', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('color', color || '');
    appendCsrfToken(fd);

    const modalWrapper = document.getElementById('modalContentWrapper');
    if (modalWrapper) {
        modalWrapper.style.backgroundColor = color || '';
        modalWrapper.style.setProperty('--note-individual-color', color || '');
    }

    if (!navigator.onLine) {
        queueAction('api/change_color.php', fd);
        showToast('Đã lưu thay đổi màu (offline)', 'success');
        liveSearch();
        return;
    }

    fetch('api/change_color.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                showToast('Đã đổi màu ghi chú thành công', 'success');
                if (wsReady && currentNoteIdForWS == id) {
                    _wsSend({
                        type: 'color_update',
                        note_id: id,
                        color: color || '',
                        user_name: currentUserName
                    });
                }
                liveSearch();
            } else {
                showAlert(d.message || 'Không thể đổi màu!', 'danger');
                if (modalWrapper) {
                    modalWrapper.style.backgroundColor = '';
                    modalWrapper.style.removeProperty('--note-individual-color');
                }
            }
        })
        .catch(() => {
            showAlert('Lỗi kết nối, vui lòng thử lại!', 'danger');
            if (modalWrapper) {
                modalWrapper.style.backgroundColor = '';
                modalWrapper.style.removeProperty('--note-individual-color');
            }
        });
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
        scheduleOfflineSyncStateEvent();
        if (navigator.onLine) {
            setTimeout(syncOfflineNotes, 2000);
        }
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
        isTemp:        isNoteTemp(note.id),
        syncStatus:    'pending',
        updated_at:    new Date().toISOString(),
        retryCount:    0,
        nextRetryAt:   undefined,
        lastSyncError: undefined
    };
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(noteToSave);
    console.log(`[Offline] Saved note ${note.id}`);
    scheduleOfflineSyncStateEvent();
}

function putOfflineNote(note) {
    if (!db) return;
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(note);
}

async function bumpOfflineRetry(note, message) {
    const retry = (note.retryCount || 0) + 1;
    const exp   = Math.min(6, Math.max(0, retry - 1));
    const delay = Math.min(OFFLINE_SYNC_MAX_DELAY_MS, OFFLINE_SYNC_BASE_DELAY_MS * Math.pow(2, exp));
    putOfflineNote({
        ...note,
        syncStatus:    'error',
        retryCount:    retry,
        nextRetryAt:   Date.now() + delay,
        lastSyncError: typeof message === 'string' ? message : 'Lỗi đồng bộ'
    });
    scheduleOfflineSyncRetrySweep();
}

let offlineSyncSweepTimer = null;

function scheduleOfflineSyncRetrySweep() {
    clearTimeout(offlineSyncSweepTimer);
    offlineSyncSweepTimer = null;
    if (!navigator.onLine || !db) return;
    setTimeout(() => {
        getAllOfflineNotes().then((notes) => {
            const now = Date.now();
            let minWait = null;
            for (const n of notes) {
                if (n.nextRetryAt && n.nextRetryAt > now) {
                    const w = n.nextRetryAt - now + 300;
                    if (minWait === null || w < minWait) minWait = w;
                }
            }
            if (minWait == null) return;
            offlineSyncSweepTimer = setTimeout(() => {
                offlineSyncSweepTimer = null;
                if (navigator.onLine) syncOfflineNotes();
            }, minWait);
        });
    }, 0);
}

function scheduleOfflineSyncStateEvent() {
    if (typeof queueMicrotask === 'function') {
        queueMicrotask(() => { emitOfflineSyncStateEvent(); });
    } else {
        setTimeout(() => { emitOfflineSyncStateEvent(); }, 0);
    }
}

async function getOfflineSyncSummary() {
    const notes = await getAllOfflineNotes();
    const now   = Date.now();
    let errorCount = 0;
    let backoffCount = 0;
    for (const n of notes) {
        if (n.syncStatus === 'error') errorCount++;
        if (n.nextRetryAt && n.nextRetryAt > now) backoffCount++;
    }
    return {
        total:          notes.length,
        errorCount,
        backoffCount,
        readyCount:     Math.max(0, notes.length - backoffCount),
        syncing:        offlineSyncIsRunning
    };
}

function emitOfflineSyncStateEvent() {
    getOfflineSyncSummary().then((detail) => {
        try {
            window.dispatchEvent(new CustomEvent('noteapp:offline-sync', { detail }));
        } catch (e) { /* ignore */ }
    });
}

if (typeof window !== 'undefined') {
    window.getNoteAppOfflineSyncSummary = getOfflineSyncSummary;
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

    const now        = Date.now();
    const toProcess  = offlineNotes.filter((n) => !n.nextRetryAt || n.nextRetryAt <= now);
    if (toProcess.length === 0) {
        scheduleOfflineSyncStateEvent();
        scheduleOfflineSyncRetrySweep();
        return;
    }

    console.log(`[Offline Sync] Found ${offlineNotes.length} notes (${toProcess.length} due now)`);
    offlineSyncIsRunning = true;
    emitOfflineSyncStateEvent();

    let syncedCount              = 0;
    let conflictStillPending    = 0;

    for (const note of toProcess) {
        let work               = { ...note };
        let unresolvedConflict = false;

        for (let attempt = 0; attempt < 4; attempt++) {
            try {
                const fd = new FormData();
                fd.append('id',         isNoteTemp(work.id) ? 0 : work.id);
                fd.append('title',      work.title   || '');
                fd.append('content',    work.content || '');
                fd.append('version',    String(work.version != null && work.version !== '' ? work.version : 1));
                appendCsrfToken(fd);

                const res = await fetch('api/save_note.php', { method: 'POST', body: fd });

                let data;
                try {
                    data = await res.json();
                } catch (parseErr) {
                    console.error('Sync failed for note', work.id, parseErr);
                    unresolvedConflict = false;
                    await bumpOfflineRetry(work, 'Phản hồi máy chủ không hợp lệ');
                    break;
                }

                if (!res.ok) {
                    unresolvedConflict = false;
                    await bumpOfflineRetry(work, `Lỗi HTTP ${res.status}`);
                    break;
                }

                if (data.success) {
                    unresolvedConflict = false;
                    syncedCount++;
                    const delTx = db.transaction(['notes'], 'readwrite');
                    delTx.objectStore('notes').delete(work.id);
                    console.log(`[Offline] Synced ${work.id} → ${data.note_id}`);
                    break;
                }

                if (data.conflict) {
                    unresolvedConflict = true;
                    const serverVer = parseInt(data.version, 10);
                    work = {
                        ...work,
                        title:         work.title,
                        content:       work.content,
                        version:       Number.isFinite(serverVer) ? serverVer : (work.version || 1),
                        syncStatus:    'pending',
                        retryCount:    0,
                        nextRetryAt:   undefined,
                        lastSyncError: undefined,
                        updated_at:    work.updated_at
                    };
                    putOfflineNote(work);
                    continue;
                }

                unresolvedConflict = false;
                await bumpOfflineRetry(work, data.message || 'Đồng bộ thất bại');
                break;
            } catch (err) {
                console.error('Sync failed for note', work.id, err);
                unresolvedConflict = false;
                await bumpOfflineRetry(work, (err && err.message) || 'Lỗi mạng');
                break;
            }
        }

        if (unresolvedConflict) {
            conflictStillPending++;
            await bumpOfflineRetry(work, 'Xung đột phiên bản lặp lại; giữ bản cục bộ và chờ thử lại');
        }
    }

    offlineSyncIsRunning = false;
    emitOfflineSyncStateEvent();
    scheduleOfflineSyncRetrySweep();

    if (syncedCount > 0) {
        showToast(`Đã đồng bộ ${syncedCount} ghi chú từ chế độ offline`, 'success');
        setTimeout(liveSearch, 800);
    }
    if (conflictStillPending > 0) {
        showToast(
            conflictStillPending === 1
                ? 'Một ghi chú offline vẫn xung đột phiên bản sau vài lần thử; giữ bản cục bộ và sẽ thử lại sau.'
                : `${conflictStillPending} ghi chú offline vẫn xung đột phiên bản sau vài lần thử; giữ bản cục bộ và sẽ thử lại sau.`,
            'warning'
        );
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