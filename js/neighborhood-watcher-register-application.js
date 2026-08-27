(function (global) {
    'use strict';

    var MAX_FILE_BYTES = 10 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    var ELIGIBILITY_KEYS = [
        'eligibility_1',
        'eligibility_2',
        'eligibility_3',
        'eligibility_4',
        'eligibility_5',
        'eligibility_6'
    ];
    var capturedEligibilityAnswers = null;
    var submitting = false;

    function $(id) {
        return document.getElementById(id);
    }

    function isAllowedImageFile(file) {
        if (!file) return false;
        var type = String(file.type || '').toLowerCase();
        if (ALLOWED_IMAGE_TYPES.indexOf(type) !== -1) return true;
        if (type.indexOf('image/') === 0 && /\.(jpe?g|png|webp)$/i.test(file.name || '')) return true;
        // Some browsers (esp. Windows) report an empty MIME type for valid images.
        return /\.(jpe?g|png|webp)$/i.test(file.name || '');
    }

    function isAllowedPdfFile(file) {
        if (!file) return false;
        var type = String(file.type || '').toLowerCase();
        if (type === 'application/pdf') return true;
        return /\.pdf$/i.test(file.name || '');
    }

    function isAllowedClearanceFile(file) {
        return isAllowedImageFile(file) || isAllowedPdfFile(file);
    }

    function validateImageFile(file, label) {
        if (!file) {
            return (label || 'Photo') + ' is required.';
        }
        if (!isAllowedImageFile(file)) {
            return (label || 'Photo') + ' must be a JPG or PNG image.';
        }
        if (file.size > MAX_FILE_BYTES) {
            return (label || 'Photo') + ' must be 10 MB or below.';
        }
        return null;
    }

    function validateClearanceFile(file) {
        if (!file) {
            return 'Barangay Clearance is required.';
        }
        if (!isAllowedClearanceFile(file)) {
            return 'Barangay Clearance must be a photo (JPG/PNG) or PDF.';
        }
        if (file.size > MAX_FILE_BYTES) {
            return 'Barangay Clearance must be 10 MB or below.';
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
            return firstName + ' ' + middleName + ' ' + lastName;
        }
        return firstName + ' ' + lastName;
    }

    function getAddressParts() {
        return {
            unit_street: String(($('memberUnitStreet') || {}).value || '').trim(),
            subdivision: String(($('memberSubdivision') || {}).value || '').trim(),
            barangay: String(($('memberBarangay') || {}).value || '').trim(),
            city: String(($('memberCity') || {}).value || '').trim(),
            postal_code: String(($('memberPostalCode') || {}).value || '').trim(),
            country: String(($('memberCountry') || {}).value || '').trim()
        };
    }

    function buildFormattedAddress(parts) {
        var chunks = [];
        if (parts.unit_street) chunks.push(parts.unit_street);
        if (parts.subdivision) chunks.push(parts.subdivision);
        if (parts.barangay) chunks.push('Barangay ' + parts.barangay);
        if (parts.city) chunks.push(parts.city);
        if (parts.postal_code) chunks.push(parts.postal_code);
        if (parts.country) chunks.push(parts.country);
        return chunks.join(', ');
    }

    function updatePostalCodeFromAddress() {
        var postal = $('memberPostalCode');
        if (!postal) return;
        var barangay = String(($('memberBarangay') || {}).value || '').trim();
        var city = String(($('memberCity') || {}).value || '').trim();
        var country = String(($('memberCountry') || {}).value || '').trim();
        if (barangay === 'San Agustin' && city === 'Quezon City' && country === 'Philippines') {
            postal.value = '1117';
        } else if (!barangay && !city && !country) {
            postal.value = '';
        }
        updateRegisterSubmitState();
    }

    function isFormComplete() {
        var lastName = ($('memberLastName') || {}).value;
        var firstName = ($('memberFirstName') || {}).value;
        var gender = ($('memberGender') || {}).value;
        var maritalStatus = ($('memberMaritalStatus') || {}).value;
        var address = getAddressParts();
        var contact = ($('memberContact') || {}).value;
        var email = ($('memberEmail') || {}).value;
        var idNumber = ($('memberIdNumber') || {}).value;
        var emergencyName = ($('memberEmergencyName') || {}).value;
        var emergencyContact = ($('memberEmergencyContact') || {}).value;
        var photo = $('memberPhoto');
        var photoId = $('memberPhotoId');
        var clearance = $('memberBarangayClearance');
        var consent = $('memberConsent');

        return !!(
            String(lastName || '').trim() &&
            String(firstName || '').trim() &&
            String(gender || '').trim() &&
            String(maritalStatus || '').trim() &&
            address.unit_street &&
            address.subdivision &&
            address.barangay &&
            address.city &&
            address.postal_code &&
            address.country &&
            String(contact || '').trim() &&
            String(email || '').trim() &&
            isBirthdayValidForSubmit() &&
            String(idNumber || '').trim() &&
            String(emergencyName || '').trim() &&
            String(emergencyContact || '').trim() &&
            photo && photo.files && photo.files[0] &&
            photoId && photoId.files && photoId.files[0] &&
            clearance && clearance.files && clearance.files[0] &&
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

    function getEligibilityAnswers() {
        var form = $('eligibilityCriteriaForm');
        if (!form) return null;
        var answers = {};
        var complete = true;
        ELIGIBILITY_KEYS.forEach(function (name) {
            var checked = form.querySelector('input[name="' + name + '"]:checked');
            if (!checked) {
                complete = false;
                return;
            }
            answers[name] = checked.value;
        });
        return complete ? answers : null;
    }

    function areAllEligibilityCriteriaMet() {
        var answers = getEligibilityAnswers();
        if (!answers) return false;
        return ELIGIBILITY_KEYS.every(function (name) {
            return answers[name] === 'yes';
        });
    }

    function updateEligibilityProceedState() {
        var proceedBtn = $('eligibilityProceedBtn');
        if (!proceedBtn) return;
        proceedBtn.disabled = !areAllEligibilityCriteriaMet();
    }

    function resetEligibilityForm() {
        var form = $('eligibilityCriteriaForm');
        if (form) form.reset();
        updateEligibilityProceedState();
    }

    function openEligibilityModal() {
        if (typeof closeLoginModal === 'function') {
            closeLoginModal();
        }
        var modal = $('eligibilityModal');
        if (!modal) return;
        capturedEligibilityAnswers = null;
        resetEligibilityForm();
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeEligibilityModal() {
        var modal = $('eligibilityModal');
        if (!modal) return;
        modal.classList.remove('open');
        resetEligibilityForm();
        if (![].slice.call(document.querySelectorAll('.modal-overlay.open')).length) {
            document.body.style.overflow = '';
        }
    }

    function openApplicationFormModal() {
        var modal = $('registerModal');
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        updateRegisterSubmitState();
        var lastName = $('memberLastName');
        if (lastName) setTimeout(function () { lastName.focus(); }, 120);
    }

    function proceedToApplicationForm() {
        if (!areAllEligibilityCriteriaMet()) {
            updateEligibilityProceedState();
            return;
        }
        capturedEligibilityAnswers = getEligibilityAnswers();
        closeEligibilityModal();
        openApplicationFormModal();
    }

    function openRegisterModal() {
        openEligibilityModal();
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

    function previewBarangayClearance(input, previewId) {
        var preview = $(previewId);
        if (!preview) return;
        preview.innerHTML = '';

        if (!input.files || !input.files[0]) {
            preview.style.display = 'none';
            updateRegisterSubmitState();
            return;
        }

        var file = input.files[0];
        var error = validateClearanceFile(file);
        if (error) {
            input.value = '';
            preview.style.display = 'none';
            if (typeof showSuccessModal === 'function') {
                showSuccessModal('Validation Error', error, true);
            }
            updateRegisterSubmitState();
            return;
        }

        if (isAllowedPdfFile(file)) {
            var doc = document.createElement('div');
            doc.className = 'file-preview-doc';
            doc.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i><span></span>';
            doc.querySelector('span').textContent = file.name || 'Barangay Clearance PDF';
            preview.appendChild(doc);
            preview.style.display = 'block';
            updateRegisterSubmitState();
            return;
        }

        var img = document.createElement('img');
        img.alt = file.name || 'Barangay Clearance';
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
            if (!isAllowedImageFile(file)) {
                reject(new Error('Invalid image file. Please use a JPG or PNG photo.'));
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    try {
                        var width = img.width;
                        var height = img.height;
                        if (!width || !height) {
                            reject(new Error('Could not read image dimensions. Please try another photo.'));
                            return;
                        }
                        if (width > maxWidth) {
                            height = Math.round(height * (maxWidth / width));
                            width = maxWidth;
                        }
                        var canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        var ctx = canvas.getContext('2d');
                        if (!ctx) {
                            reject(new Error('Unable to process image in this browser.'));
                            return;
                        }
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    } catch (err) {
                        reject(new Error('Unable to process image. Please try another JPG or PNG photo.'));
                    }
                };
                img.onerror = function () {
                    reject(new Error('Failed to load image. Please use a JPG or PNG photo.'));
                };
                img.src = e.target.result;
            };
            reader.onerror = function () {
                reject(new Error('Failed to read image file.'));
            };
            reader.readAsDataURL(file);
        });
    }

    function readFileAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function (e) {
                resolve(e.target.result);
            };
            reader.onerror = function () {
                reject(new Error('Failed to read file.'));
            };
            reader.readAsDataURL(file);
        });
    }

    function prepareClearanceDataUrl(file) {
        if (isAllowedPdfFile(file)) {
            return readFileAsDataUrl(file);
        }
        return compressImageFile(file);
    }

    function parseJsonResponse(res) {
        return res.text().then(function (text) {
            var data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (err) {
                throw new Error('Server returned an unexpected response. Please try again.');
            }
            if (!res.ok) {
                throw new Error((data && data.message) || ('Request failed (' + res.status + '). Please try again.'));
            }
            if (!data) {
                throw new Error('Empty server response. Please try again.');
            }
            return data;
        });
    }

    function closeRegisterModal() {
        var modal = $('registerModal');
        if (!modal) return;
        modal.classList.remove('open');
        var form = $('memberApplicationForm');
        if (form) form.reset();
        ['memberPhotoPreview', 'memberPhotoIdPreview', 'memberBarangayClearancePreview'].forEach(function (id) {
            var preview = $(id);
            if (!preview) return;
            preview.style.display = 'none';
            preview.innerHTML = '';
        });
        capturedEligibilityAnswers = null;
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
        var firstName = $('memberFirstName').value.trim();
        var middleName = ($('memberMiddleName') || {}).value ? $('memberMiddleName').value.trim() : '';
        var lastName = $('memberLastName').value.trim();
        var gender = ($('memberGender') || {}).value ? $('memberGender').value.trim() : '';
        var maritalStatus = ($('memberMaritalStatus') || {}).value ? $('memberMaritalStatus').value.trim() : '';
        var addressParts = getAddressParts();
        var address = buildFormattedAddress(addressParts);
        var contact = $('memberContact').value.trim();
        var email = $('memberEmail').value.trim();
        var birthdayDate = parseBirthdayDate(($('memberBirthday') || {}).value);
        var birthday = birthdayDate ? toIsoDate(birthdayDate) : '';
        var idNumber = $('memberIdNumber').value.trim();
        var emergencyName = $('memberEmergencyName').value.trim();
        var emergencyContact = $('memberEmergencyContact').value.trim();
        var photoFile = $('memberPhoto').files[0];
        var photoIdFile = $('memberPhotoId').files[0];
        var clearanceFile = $('memberBarangayClearance').files[0];
        var consent = $('memberConsent');
        var submitBtn = $('registerSubmitBtn');

        updateBirthdayAgeMessage();
        updatePostalCodeFromAddress();
        addressParts = getAddressParts();
        address = buildFormattedAddress(addressParts);

        if (!name) {
            showSuccessModal('Validation Error', 'Please enter last name and first name.', true);
            updateRegisterSubmitState();
            return;
        }

        if (!addressParts.unit_street || !addressParts.subdivision || !addressParts.barangay || !addressParts.city || !addressParts.postal_code || !addressParts.country) {
            showSuccessModal('Validation Error', 'Please complete all address fields.', true);
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

        if (!capturedEligibilityAnswers) {
            showSuccessModal('Validation Error', 'Please complete the eligibility criteria before submitting.', true);
            openEligibilityModal();
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
        var clearanceError = validateClearanceFile(clearanceFile);
        if (clearanceError) {
            showSuccessModal('Validation Error', clearanceError, true);
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

        Promise.all([
            compressImageFile(photoFile),
            compressImageFile(photoIdFile),
            prepareClearanceDataUrl(clearanceFile)
        ])
            .then(function (results) {
                return fetch('api/neighborhood-watcher-members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        name: name,
                        first_name: firstName,
                        middle_name: middleName,
                        last_name: lastName,
                        gender: gender,
                        marital_status: maritalStatus,
                        contact: contact,
                        email: email,
                        address: address,
                        address_unit_street: addressParts.unit_street,
                        address_subdivision: addressParts.subdivision,
                        address_barangay: addressParts.barangay,
                        address_city: addressParts.city,
                        address_postal_code: addressParts.postal_code,
                        address_country: addressParts.country,
                        birthday: birthday,
                        id_number: idNumber,
                        status: 'Pending',
                        photo: results[0],
                        photo_id: results[1],
                        barangay_clearance: results[2],
                        eligibility_answers: capturedEligibilityAnswers,
                        emergency_contact_name: emergencyName,
                        emergency_contact_number: emergencyContact
                    })
                });
            })
            .then(parseJsonResponse)
            .then(function (result) {
                if (!result.success) {
                    showSuccessModal('Error', result.message || 'Failed to submit application. Please try again.', true);
                    return;
                }
                capturedEligibilityAnswers = null;
                closeRegisterModal();
                setTimeout(showRegistrationSuccessModal, 250);
            })
            .catch(function (err) {
                console.error('Error submitting neighborhood watch application:', err);
                var message = (err && err.message) ? String(err.message) : 'Unable to submit application. Please try again.';
                showSuccessModal('Error', message, true);
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

        ['memberSubdivision', 'memberBarangay', 'memberCity', 'memberCountry'].forEach(function (id) {
            var el = $(id);
            if (!el) return;
            el.addEventListener('change', function () {
                updatePostalCodeFromAddress();
            });
        });

        var unitStreet = $('memberUnitStreet');
        if (unitStreet) {
            unitStreet.addEventListener('input', updateRegisterSubmitState);
        }

        var eligibilityForm = $('eligibilityCriteriaForm');
        if (eligibilityForm) {
            eligibilityForm.addEventListener('change', updateEligibilityProceedState);
        }
        updateEligibilityProceedState();
        updatePostalCodeFromAddress();

        updateRegisterSubmitState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindForm);
    } else {
        bindForm();
    }

    global.openRegisterModal = openRegisterModal;
    global.closeRegisterModal = closeRegisterModal;
    global.openEligibilityModal = openEligibilityModal;
    global.closeEligibilityModal = closeEligibilityModal;
    global.proceedToApplicationForm = proceedToApplicationForm;
    global.showRegistrationSuccessModal = showRegistrationSuccessModal;
    global.closeRegistrationSuccessModal = closeRegistrationSuccessModal;
    global.previewMemberImage = previewMemberImage;
    global.previewBarangayClearance = previewBarangayClearance;
    global.submitMemberApplication = submitMemberApplication;
    global.updateRegisterSubmitState = updateRegisterSubmitState;
})(window);
