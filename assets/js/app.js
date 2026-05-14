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
let autoSaveTimer          = null;
let autoSaveRetryTimer     = null;
let autoSaveBusyTimer      = null;
let autoSaveInFlightSeq    = 0;
let lastAutoSavePersistSig = '';
let isSaving               = false;
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
    // --- Modals Bootstrap ---
    noteModal          = new bootstrap.Modal(document.getElementById('noteModal'));
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
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
    currentPermission = permission;
    currentNoteId     = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl = document.getElementById('noteContent');
    delete contentEl.dataset.version;

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
            .catch(() => {})
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
    clearTimeout(liveSearchAfterSaveTimer);
    liveSearchAfterSaveTimer = null;
    stopRealtime();
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
// Tích hợp: offline fallback + WebSocket broadcast + xử lý conflict
// =========================================================
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

            // --- OFFLINE: lưu cục bộ ngay lập tức ---
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

            // --- ONLINE: gửi lên server ---
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

                        const titleEl = document.getElementById('noteTitle');
                        const hasLatestTitle   = d.latest_title !== undefined;
                        const hasLatestContent = d.latest_content !== undefined;

                        noteConflictResolutionLock = true;
                        window.__remoteUpdating = true;
                        try {
                            // Apply server fields first; only then bump version (never version without synced title+body).
                            if (hasLatestTitle) {
                                titleEl.value = d.latest_title;
                            }
                            if (hasLatestContent) {
                                contentEl.value = d.latest_content;
                            }
                            if (hasLatestTitle && hasLatestContent &&
                                d.version !== undefined && d.version !== null && d.version !== '') {
                                contentEl.dataset.version = String(d.version);
                            }
                            lastAutoSavePersistSig = _autoSavePayloadSignature();
                        } finally {
                            window.__remoteUpdating = false;
                            noteConflictResolutionLock = false;
                        }

                        statusEl.innerHTML = '<i class="bi bi-arrow-clockwise text-warning"></i> Đồng bộ từ máy chủ';
                        showToast(
                            'Nội dung đã được đồng bộ với bản mới nhất trên máy chủ. Tiếp tục chỉnh sửa để lưu.',
                            'warning'
                        );
                        return;
                    }

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
            appendCsrfToken(fd);

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
            appendCsrfToken(fd);

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
            appendCsrfToken(fd);

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
                        // Reset modal password về dạng nhập mật khẩu mở note
                        resetPasswordModalToDefault();
                        liveSearch();
                    }
                })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    // Khi modal bị đóng bằng nút Hủy hoặc dấu X, cũng reset lại
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

                    if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                        if (data.user_name === currentUserName) return;

                        const contentElPre = document.getElementById('noteContent');
                        const incomingVer = data.version != null && data.version !== ''
                            ? parseInt(data.version, 10)
                            : NaN;
                        const rawLocal = contentElPre && contentElPre.dataset.version;
                        const localVer = rawLocal !== undefined && rawLocal !== ''
                            ? parseInt(rawLocal, 10)
                            : NaN;
                        if (Number.isFinite(incomingVer) && Number.isFinite(localVer) && incomingVer < localVer) {
                            return;
                        }

                        const c = String(data.content ?? '');
                        const inboundKey = [
                            data.note_id,
                            data.user_name,
                            data.timestamp ?? '',
                            data.title ?? '',
                            c.length,
                            c.slice(0, 256)
                        ].join('\x1e');
                        if (inboundKey === _lastWsInboundKey) return;
                        _lastWsInboundKey = inboundKey;

                        const titleEl   = document.getElementById('noteTitle');
                        const contentEl = document.getElementById('noteContent');

                        const isEditingTitle   = document.activeElement === titleEl;
                        const isEditingContent = document.activeElement === contentEl;

                        window.__remoteUpdating = true;
                        try {
                            if (data.version != null && data.version !== '') {
                                contentEl.dataset.version = String(data.version);
                            }

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
                                        try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e3) {}
                                    }
                                }
                            }
                        } finally {
                            window.__remoteUpdating = false;
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
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('image',   f);
    fd.append('note_id', nid);
    appendCsrfToken(fd);

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
        body:    appendCsrfUrlEncoded(`name=${encodeURIComponent(name)}`)
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    appendCsrfUrlEncoded(`id=${id}&name=${encodeURIComponent(newName.trim())}`)
    }).then(() => loadFilterLabels(() => liveSearch()));
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
    appendCsrfToken(fd);

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
    const fd = new FormData();
    fd.append('id', id);
    fd.append('is_pinned', state);
    appendCsrfToken(fd);
    fetch('api/pin_note.php', { method: 'POST', body: fd })
        .then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) { showAlert('Vui lòng lưu ghi chú trước khi đổi màu!', 'warning'); return; }

    const fd = new FormData();
    fd.append('id',         id);
    fd.append('color',      color || '');
    appendCsrfToken(fd);

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