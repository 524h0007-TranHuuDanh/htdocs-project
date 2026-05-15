<!-- Modal ghi chú chính -->
<div class="modal fade" id="noteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg" id="modalContentWrapper">
            <div class="modal-header border-0 pb-0">
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent" placeholder="Tiêu đề...">
                <span id="wsStatusBadge" class="badge bg-secondary ms-2 small" style="display:none;"></span>
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <div id="wsPresenceBar" class="alert alert-success py-1 small mb-2" style="min-height:0;"></div>
                <div id="wsTypingIndicator" class="text-muted small fst-italic mb-2" style="display:none;"></div>

                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10" placeholder="Bạn đang nghĩ gì?..."></textarea>
                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mb-3"></div>

                <!-- Color -->
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

                <!-- Share -->
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

                <!-- Tools -->
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

<!-- Các modal khác giữ nguyên... (các modal profile, password, delete, custom, bulk, reset, rename) -->
<!-- Modal Profile MỚI (thay thế hoàn toàn) -->
<div class="modal fade" id="profileModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg" id="profileModalContent">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="profileModalTitle">
                    <i class="bi bi-person-circle"></i> Tài khoản
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="profileModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
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
                <button type="button" id="passwordModalConfirmBtn" class="btn btn-primary" onclick="submitNotePassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Xác nhận Xóa -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="deleteModalTitle">
                    <i class="bi bi-trash3"></i> Xác nhận xóa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteModalBody">
                <!-- Nội dung sẽ được thay đổi bằng JS -->
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Xác nhận</button>
            </div>
        </div>
    </div>
</div>
<!-- ==================== CUSTOM ALERT / CONFIRM MODAL ==================== -->
<div class="modal fade" id="customAlertModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="customAlertTitle">Thông báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4" id="customAlertBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary px-4" id="customAlertBtn" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title text-warning" id="customConfirmTitle">Xác nhận</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customConfirmBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" id="confirmCancelBtn">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirmOkBtn">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Bulk Share -->
<div class="modal fade" id="bulkShareModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-share-fill"></i> Chia sẻ nhiều ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Email người nhận (cách nhau bằng dấu phẩy)</label>
                    <input type="text" id="bulkShareEmails" class="form-control" placeholder="user1@example.com, user2@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Quyền truy cập</label>
                    <select id="bulkSharePermission" class="form-select">
                        <option value="read">Chỉ xem</option>
                        <option value="edit">Cho phép chỉnh sửa</option>
                    </select>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> Ghi chú sẽ được chia sẻ đến tất cả email trên, với quyền được chọn.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="bulkShareConfirmBtn">Xác nhận chia sẻ</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal chọn phương thức reset mật khẩu (dùng trong profile) -->
<div class="modal fade" id="resetChoiceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-primary">Khôi phục mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-4">Chọn phương thức nhận mã khôi phục mật khẩu:</p>
                <div class="d-grid gap-3">
                    <button id="resetChoiceOtpBtn" class="btn btn-outline-primary btn-lg py-3">
                        <i class="bi bi-envelope-paper fs-4 me-2"></i> Gửi mã OTP
                    </button>
                    <button id="resetChoiceLinkBtn" class="btn btn-outline-success btn-lg py-3">
                        <i class="bi bi-link-45deg fs-4 me-2"></i> Gửi link đặt lại mật khẩu
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Đổi tên nhãn -->
<div class="modal fade" id="renameLabelModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square"></i> Đổi tên nhãn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên nhãn mới</label>
                    <input type="text" id="renameLabelInput" class="form-control" placeholder="Nhập tên nhãn..." autocomplete="off">
                    <div id="renameLabelError" class="text-danger small mt-1" style="display:none;"></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" id="renameLabelConfirmBtn" class="btn btn-primary">Lưu</button>
            </div>
        </div>
    </div>
</div>