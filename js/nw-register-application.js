(function (global) {
    'use strict';

    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    var submitting = false;

    function $(id) {
        return document.getElementById(id);
    }

    function validateImageFile(file, label) {
        if (!file) {
            return (label || 'Photo') + ' is required.';
        }
        if (!ALLOWED_IMAGE_TYPES.includes(String(file.type || '').toLowerCase()) && !/\.(jpe?g|png|webp)$/i.test(file.name || '')) {
            return (label || 'Photo') + ' must be a JPG or PNG image.';
        }
        if (file.size > MAX_IMAGE_BYTES) {
            return (label || 'Photo') + ' must be 5 MB or below.';
        }
        return null;
    }

    function formatBirthdayMmDdYyyy(value) {
        var digits = String(value || '').replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 2) return digits;
        if (digits.length <= 4) return digits.slice(0, 2) + '/' + digits.slice(2);
        return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
    }

    function toIsoDate(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    function toMmDdYyyy(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return month + '/' + day + '/' + date.getFullYear();
    }

    function parseBirthdayDate(value) {
        var raw = String(value || '').trim();
        var match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(raw);
        if (!match) {
            match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
            if (!match) return null;
            // YYYY-MM-DD from calendar
            return parseBirthdayParts(parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10));
        }
        // MM/DD/YYYY
        return parseBirthdayParts(parseInt(match[3], 10), parseInt(match[1], 10), parseInt(match[2], 10));
    }

    function parseBirthdayParts(year, month, day) {
        if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900) {
            return null;
        }

        var date = new Date(year, month - 1, day);
        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            return null;
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (date > today) return null;

        return date;
    }

    function calculateAge(birthDate) {
        var today = new Date();
        var age = today.getFullYear() - birthDate.getFullYear();
        var monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age -= 1;
        }
        return age;
    }

    function syncBirthdayPicker(birthDate) {
        var picker = $('memberBirthdayPicker');
        if (!picker) return;
        picker.value = birthDate ? toIsoDate(birthDate) : '';
    }

    function updateBirthdayAgeMessage() {
        var input = $('memberBirthday');
        var errorEl = $('memberBirthdayError');
        if (!input || !errorEl) return true;

        var birthDate = parseBirthdayDate(input.value);
        var underAge = !!(birthDate && calculateAge(birthDate) < 18);

        errorEl.hidden = !underAge;
        input.classList.toggle('input-invalid', underAge);
        return !underAge;
    }

    function isBirthdayValidForSubmit() {
        var birthDate = parseBirthdayDate(($('memberBirthday') || {}).value);
        return !!(birthDate && calculateAge(birthDate) >= 18);
    }

    function getCombinedFullName() {
        var lastName = String(($('memberLastName') || {}).value || '').trim();
        var firstName = String(($('memberFirstName') || {}).value || '').trim();
        var middleName = String(($('memberMiddleName') || {}).value || '').trim();
        if (!lastName || !firstName) return '';
        if (middleName) {
            return lastName + ', ' + firstName + ' ' + middleName;
        }
        return lastName + ', ' + firstName;
    }

    function isFormComplete() {
        var lastName = ($('memberLastName') || {}).value;
        var firstName = ($('memberFirstName') || {}).value;
        var contact = ($('memberContact') || {}).value;
        var email = ($('memberEmail') || {}).value;
        var address = ($('memberAddress') || {}).value;
        var idNumber = ($('memberIdNumber') || {}).value;
        var emergencyName = ($('memberEmergencyName') || {}).value;
        var emergencyContact = ($('memberEmergencyContact') || {}).value;
        var photo = $('memberPhoto');
        var photoId = $('memberPhotoId');
        var consent = $('memberConsent');

        return !!(
            String(lastName || '').trim() &&
            String(firstName || '').trim() &&
            String(contact || '').trim() &&
            String(email || '').trim() &&
            String(address || '').trim() &&
            isBirthdayValidForSubmit() &&
            String(idNumber || '').trim() &&
            String(emergencyName || '').trim() &&
            String(emergencyContact || '').trim() &&
            photo && photo.files && photo.files[0] &&
            photoId && photoId.files && photoId.files[0] &&
            consent && consent.checked
        );
    }

    function updateRegisterSubmitState() {
        var submitBtn = $('registerSubmitBtn');
        if (!submitBtn) return;
        updateBirthdayAgeMessage();
        if (submitting) {
            submitBtn.disabled = true;
            return;
        }
        var complete = isFormComplete();
        submitBtn.disabled = !complete;
        if (!complete) {
            submitBtn.textContent = 'Submit Application';
        }
    }

    function previewMemberImage(input, previewId) {
        var preview = $(previewId);
        if (!preview) return;
        preview.innerHTML = '';

        if (!input.files || !input.files[0]) {
            preview.style.display = 'none';
            updateRegisterSubmitState();
            return;
        }

        var file = input.files[0];
        var label = input.id === 'memberPhotoId' ? 'Valid ID photo' : 'Member photo';
        var sizeError = validateImageFile(file, label);
        if (sizeError) {
            input.value = '';
            preview.style.display = 'none';
            if (typeof showSuccessModal === 'function') {
                showSuccessModal('Validation Error', sizeError, true);
            }
            updateRegisterSubmitState();
            return;
        }

        var img = document.createElement('img');
        img.alt = file.name;
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        preview.appendChild(img);
        preview.style.display = 'block';
        updateRegisterSubmitState();
    }

    function compressImageFile(file, maxWidth, quality) {
        maxWidth = maxWidth || 1280;
        quality = quality || 0.82;
        return new Promise(function (resolve, reject) {
            if (!file || !String(file.type || '').startsWith('image/')) {
                reject(new Error('Invalid image file'));
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    var width = img.width;
                    var height = img.height;
                    if (width > maxWidth) {
                        height = Math.round(height * (maxWidth / width));
                        width = maxWidth;
                    }
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                };
                img.onerror = function () {
                    reject(new Error('Failed to load image'));
                };
                img.src = e.target.result;
            };
            reader.onerror = function () {
                reject(new Error('Failed to read image'));
            };
            reader.readAsDataURL(file);
        });
    }

    function openRegisterModal() {
        if (typeof closeLoginModal === 'function') {
            closeLoginModal();
        }
        var modal = $('registerModal');
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        updateRegisterSubmitState();
        var lastName = $('memberLastName');
        if (lastName) setTimeout(function () { lastName.focus(); }, 120);
    }

    function closeRegisterModal() {
        var modal = $('registerModal');
        if (!modal) return;
        modal.classList.remove('open');
        var form = $('memberApplicationForm');
        if (form) form.reset();
        ['memberPhotoPreview', 'memberPhotoIdPreview'].forEach(function (id) {
            var preview = $(id);
            if (!preview) return;
            preview.style.display = 'none';
            preview.innerHTML = '';
        });
        submitting = false;
        var submitBtn = $('registerSubmitBtn');
        if (submitBtn) {
            submitBtn.textContent = 'Submit Application';
        }
        updateRegisterSubmitState();
        if (![].slice.call(document.querySelectorAll('.modal-overlay.open')).length) {
            document.body.style.overflow = '';
        }
    }

    function showRegistrationSuccessModal() {
        var modal = $('registrationSuccessModal');
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeRegistrationSuccessModal() {
        var modal = $('registrationSuccessModal');
        if (!modal) return;
        modal.classList.remove('open');
        if (![].slice.call(document.querySelectorAll('.modal-overlay.open')).length) {
            document.body.style.overflow = '';
        }
    }

    function submitMemberApplication(event) {
        event.preventDefault();
        if (submitting) return;

        var name = getCombinedFullName();
        var contact = $('memberContact').value.trim();
        var email = $('memberEmail').value.trim();
        var address = $('memberAddress').value.trim();
        var birthdayDate = parseBirthdayDate(($('memberBirthday') || {}).value);
        var birthday = birthdayDate ? toIsoDate(birthdayDate) : '';
        var idNumber = $('memberIdNumber').value.trim();
        var emergencyName = $('memberEmergencyName').value.trim();
        var emergencyContact = $('memberEmergencyContact').value.trim();
        var photoFile = $('memberPhoto').files[0];
        var photoIdFile = $('memberPhotoId').files[0];
        var consent = $('memberConsent');
        var submitBtn = $('registerSubmitBtn');

        updateBirthdayAgeMessage();

        if (!name) {
            showSuccessModal('Validation Error', 'Please enter last name and first name.', true);
            updateRegisterSubmitState();
            return;
        }

        if (!birthdayDate) {
            showSuccessModal('Validation Error', 'Please enter birthday in MM/DD/YYYY format.', true);
            updateRegisterSubmitState();
            return;
        }

        if (calculateAge(birthdayDate) < 18) {
            updateRegisterSubmitState();
            return;
        }

        if (!isFormComplete()) {
            showSuccessModal('Validation Error', 'Please complete all required fields and agree to the Terms and Data Privacy policy.', true);
            updateRegisterSubmitState();
            return;
        }

        if (!consent.checked) {
            showSuccessModal('Validation Error', 'Please tick the Terms and Agreement and Data Privacy consent box before submitting.', true);
            return;
        }

        var photoError = validateImageFile(photoFile, 'Member photo');
        if (photoError) {
            showSuccessModal('Validation Error', photoError, true);
            return;
        }
        var photoIdError = validateImageFile(photoIdFile, 'Valid ID photo');
        if (photoIdError) {
            showSuccessModal('Validation Error', photoIdError, true);
            return;
        }

        var contactError = global.AlertaraFormEnhancements.validateContactInput($('memberContact'), 'Contact number');
        if (contactError) {
            showSuccessModal('Validation Error', contactError, true);
            return;
        }
        var emergencyContactError = global.AlertaraFormEnhancements.validateContactInput($('memberEmergencyContact'), 'Emergency contact number');
        if (emergencyContactError) {
            showSuccessModal('Validation Error', emergencyContactError, true);
            return;
        }

        submitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }

        Promise.all([compressImageFile(photoFile), compressImageFile(photoIdFile)])
            .then(function (results) {
                return fetch('api/nw_members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        name: name,
                        contact: contact,
                        email: email,
                        address: address,
                        birthday: birthday,
                        id_number: idNumber,
                        status: 'Pending',
                        photo: results[0],
                        photo_id: results[1],
                        emergency_contact_name: emergencyName,
                        emergency_contact_number: emergencyContact
                    })
                });
            })
            .then(function (res) {
                return res.json();
            })
            .then(function (result) {
                if (!result.success) {
                    showSuccessModal('Error', result.message || 'Failed to submit application. Please try again.', true);
                    return;
                }
                closeRegisterModal();
                setTimeout(showRegistrationSuccessModal, 250);
            })
            .catch(function (err) {
                console.error('Error submitting neighborhood watch application:', err);
                showSuccessModal('Error', 'Unable to process photos. Please use JPG or PNG images of 5 MB or below and try again.', true);
            })
            .finally(function () {
                submitting = false;
                if (submitBtn) {
                    submitBtn.textContent = 'Submit Application';
                }
                updateRegisterSubmitState();
            });
    }

    function bindForm() {
        var form = $('memberApplicationForm');
        if (!form) return;

        form.addEventListener('input', updateRegisterSubmitState);
        form.addEventListener('change', updateRegisterSubmitState);

        var birthdayInput = $('memberBirthday');
        var birthdayPicker = $('memberBirthdayPicker');

        if (birthdayInput) {
            birthdayInput.addEventListener('input', function () {
                var formatted = formatBirthdayMmDdYyyy(birthdayInput.value);
                if (birthdayInput.value !== formatted) {
                    birthdayInput.value = formatted;
                }
                syncBirthdayPicker(parseBirthdayDate(formatted));
                updateRegisterSubmitState();
            });
            birthdayInput.addEventListener('change', updateRegisterSubmitState);
            birthdayInput.addEventListener('blur', function () {
                var birthDate = parseBirthdayDate(birthdayInput.value);
                if (birthDate) {
                    birthdayInput.value = toMmDdYyyy(birthDate);
                    syncBirthdayPicker(birthDate);
                }
                updateRegisterSubmitState();
            });
        }

        if (birthdayPicker) {
            birthdayPicker.addEventListener('change', function () {
                var birthDate = parseBirthdayDate(birthdayPicker.value);
                if (birthdayInput && birthDate) {
                    birthdayInput.value = toMmDdYyyy(birthDate);
                } else if (birthdayInput && !birthdayPicker.value) {
                    birthdayInput.value = '';
                }
                updateRegisterSubmitState();
            });
        }

        var consent = $('memberConsent');
        if (consent) {
            consent.addEventListener('change', updateRegisterSubmitState);
        }

        updateRegisterSubmitState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindForm);
    } else {
        bindForm();
    }

    global.openRegisterModal = openRegisterModal;
    global.closeRegisterModal = closeRegisterModal;
    global.showRegistrationSuccessModal = showRegistrationSuccessModal;
    global.closeRegistrationSuccessModal = closeRegistrationSuccessModal;
    global.previewMemberImage = previewMemberImage;
    global.submitMemberApplication = submitMemberApplication;
    global.updateRegisterSubmitState = updateRegisterSubmitState;
})(window);
