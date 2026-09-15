<?php
// profile_modal.php — the "My Profile" modal, triggered from topbar.php's user
// dropdown. Included at the very end of <body> deliberately: it must NOT live
// inside .top-navbar (position:sticky + z-index creates a stacking context that
// traps a nested modal below Bootstrap's .modal-backdrop, making it unclickable).
// Requires: $currentUser
$pmUser    = $currentUser ?? ['full_name' => 'User', 'role' => 'user', 'avatar_path' => null];
$pmInitial = strtoupper(substr($pmUser['full_name'], 0, 1));
?>
<!-- My Profile Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="pmForm">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="profile-avatar-wrap">
                                <div class="profile-avatar" id="pmAvatarWrap">
                                    <img id="pmAvatarImg" class="d-none" src="" alt="">
                                    <span id="pmAvatarInitial"><?= e($pmInitial) ?></span>
                                </div>
                                <label class="profile-avatar-edit d-none" for="pmAvatarInput" id="pmAvatarEditBtn" title="Change photo"><i class="fas fa-camera"></i></label>
                                <input type="file" id="pmAvatarInput" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none">
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="profile-section-title"><i class="fas fa-circle-info"></i> Personal Information</div>
                            <hr class="mt-2 mb-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control pm-field" id="pmFirstName" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control pm-field" id="pmMiddleName" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control pm-field" id="pmLastName" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name Extension</label>
                                    <input type="text" class="form-control pm-field" id="pmSuffix" placeholder="Jr., Sr., III, etc." readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="pmUsername" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" id="pmRole" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control pm-field" id="pmDepartment" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" id="pmMemberSince" readonly disabled>
                                </div>
                            </div>

                            <div class="profile-section-title mt-4"><i class="fas fa-shield-halved"></i> Account Security</div>
                            <hr class="mt-2 mb-1">
                            <div class="profile-security-row">
                                <span class="psr-label">Last Login:</span>
                                <span class="text-muted" id="pmLastLogin">—</span>
                            </div>
                            <div class="profile-security-row">
                                <span class="psr-label">Account Status:</span>
                                <span id="pmAccountStatus">—</span>
                            </div>

                            <div id="pmPasswordSection" class="d-none mt-4">
                                <div class="profile-section-title"><i class="fas fa-key"></i> Change Password</div>
                                <div class="text-muted small mb-3">Leave blank to keep your current password.</div>
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="pmCurrentPassword" autocomplete="current-password">
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="pmNewPassword" autocomplete="new-password" placeholder="Min 6 characters">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="pmConfirmPassword" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <small id="pmReadOnlyNote" class="text-muted me-auto d-none">
                    <i class="fas fa-circle-info me-1"></i>Managed in HRMIS — ask an administrator to update your details.
                </small>
                <button type="button" class="btn btn-secondary" id="pmCloseBtn" onclick="pmCloseOrCancel()">Close</button>
                <button type="button" class="btn btn-warning text-dark" id="pmEditToggleBtn" onclick="pmToggleEditMode()"><i class="fas fa-pen me-1"></i>Edit Profile</button>
                <button type="submit" form="pmForm" class="btn btn-primary-app d-none" id="pmSaveBtn"><i class="fas fa-save me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
let pmEditMode = false;
let pmOriginal = {};

function pmFmtDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
}
function pmFmtMonthYear(iso) {
    if (!iso) return '—';
    return new Date(iso.replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
}

const PM_ROLE_LABELS = { super_admin: 'Super Administrator', admin: 'Admin', user: 'User' };

async function pmLoadProfile() {
    try {
        const res  = await fetch('api/profile.php');
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Failed to load profile.', 'error');
        const u = data.user;

        document.getElementById('pmFirstName').value    = u.first_name || '';
        document.getElementById('pmMiddleName').value    = u.middle_name || '';
        document.getElementById('pmLastName').value      = u.last_name || '';
        document.getElementById('pmSuffix').value        = u.suffix || '';
        document.getElementById('pmUsername').value      = u.username;
        document.getElementById('pmRole').value          = PM_ROLE_LABELS[u.role] || u.role;
        document.getElementById('pmDepartment').value    = u.department || '';
        document.getElementById('pmMemberSince').value   = pmFmtMonthYear(u.created_at);
        document.getElementById('pmLastLogin').textContent   = pmFmtDateTime(u.last_login);
        document.getElementById('pmAccountStatus').innerHTML = u.is_active
            ? '<span class="text-success fw-semibold">Active</span>'
            : '<span class="text-danger fw-semibold">Inactive</span>';

        // Account details live in HRMIS and are edited there (Settings > User
        // Management), so hide the Edit button entirely rather than offering
        // an action the API will refuse.
        if (u.read_only) {
            document.getElementById('pmEditToggleBtn').classList.add('d-none');
            const note = document.getElementById('pmReadOnlyNote');
            if (note) note.classList.remove('d-none');
        }

        const img = document.getElementById('pmAvatarImg');
        const initial = document.getElementById('pmAvatarInitial');
        if (u.avatar_path) {
            img.src = u.avatar_path;
            img.classList.remove('d-none');
            initial.classList.add('d-none');
        } else {
            img.classList.add('d-none');
            initial.classList.remove('d-none');
        }

        pmOriginal = { first_name: u.first_name || '', middle_name: u.middle_name || '', last_name: u.last_name || '', suffix: u.suffix || '', department: u.department || '' };
    } catch (err) {
        showToast('Failed to load profile.', 'error');
        console.error(err);
    }
}

function pmToggleEditMode() {
    pmEditMode = !pmEditMode;
    document.querySelectorAll('#pmForm .pm-field').forEach(el => el.readOnly = !pmEditMode);
    document.getElementById('pmAvatarEditBtn').classList.toggle('d-none', !pmEditMode);
    document.getElementById('pmPasswordSection').classList.toggle('d-none', !pmEditMode);
    document.getElementById('pmEditToggleBtn').classList.toggle('d-none', pmEditMode);
    document.getElementById('pmSaveBtn').classList.toggle('d-none', !pmEditMode);
    document.getElementById('pmCloseBtn').textContent = pmEditMode ? 'Cancel' : 'Close';
}

function pmCloseOrCancel() {
    if (!pmEditMode) {
        bootstrap.Modal.getInstance(document.getElementById('profileModal'))?.hide();
        return;
    }
    document.getElementById('pmFirstName').value  = pmOriginal.first_name;
    document.getElementById('pmMiddleName').value = pmOriginal.middle_name;
    document.getElementById('pmLastName').value   = pmOriginal.last_name;
    document.getElementById('pmSuffix').value     = pmOriginal.suffix;
    document.getElementById('pmDepartment').value = pmOriginal.department;
    document.getElementById('pmCurrentPassword').value = '';
    document.getElementById('pmNewPassword').value = '';
    document.getElementById('pmConfirmPassword').value = '';
    pmToggleEditMode();
}

document.getElementById('pmAvatarInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
        document.getElementById('pmAvatarImg').src = ev.target.result;
        document.getElementById('pmAvatarImg').classList.remove('d-none');
        document.getElementById('pmAvatarInitial').classList.add('d-none');
    };
    reader.readAsDataURL(file);
});

document.getElementById('pmForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const firstName = document.getElementById('pmFirstName').value.trim();
    const lastName   = document.getElementById('pmLastName').value.trim();
    if (!firstName || !lastName) return showToast('First and last name are required.', 'error');

    const form = new FormData();
    form.append('first_name', firstName);
    form.append('middle_name', document.getElementById('pmMiddleName').value.trim());
    form.append('last_name', lastName);
    form.append('suffix', document.getElementById('pmSuffix').value.trim());
    form.append('department', document.getElementById('pmDepartment').value.trim());
    form.append('current_password', document.getElementById('pmCurrentPassword').value);
    form.append('new_password', document.getElementById('pmNewPassword').value);
    form.append('confirm_password', document.getElementById('pmConfirmPassword').value);
    const avatarFile = document.getElementById('pmAvatarInput').files[0];
    if (avatarFile) form.append('avatar', avatarFile);

    const btn = document.getElementById('pmSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

    try {
        const res  = await fetch('api/profile.php', { method: 'POST', body: form });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            document.getElementById('pmCurrentPassword').value = '';
            document.getElementById('pmNewPassword').value = '';
            document.getElementById('pmConfirmPassword').value = '';
            document.getElementById('pmAvatarInput').value = '';
            await pmLoadProfile();
            pmToggleEditMode();
            pmSyncHeaderUI(data.full_name, data.avatar_path);
        }
    } catch (err) {
        showToast('Request failed. Please try again.', 'error');
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Changes';
    }
});

function pmSyncHeaderUI(fullName, avatarPath) {
    document.querySelectorAll('.sidebar-user .u-name').forEach(el => el.textContent = fullName);
    document.querySelectorAll('.user-pill .up-name').forEach(el => el.textContent = fullName);
    if (avatarPath) {
        const html = `<img src="${avatarPath}" alt="" style="width:100%;height:100%;object-fit:cover;">`;
        document.querySelectorAll('.sidebar-user .avatar').forEach(el => el.innerHTML = html);
        document.querySelectorAll('.user-pill .up-avatar').forEach(el => el.innerHTML = html);
    }
}

document.getElementById('profileModal').addEventListener('show.bs.modal', pmLoadProfile);
document.getElementById('profileModal').addEventListener('hidden.bs.modal', () => {
    if (pmEditMode) pmToggleEditMode();
});
</script>
