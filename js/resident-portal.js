(function (global) {
    'use strict';

    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    function $(id) {
        return document.getElementById(id);
    }

    function openModal(id) {
        var modal = $(id);
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        var modal = $(id);
        if (!modal) return;
        modal.classList.remove('open');
        if (![].slice.call(document.querySelectorAll('.modal-overlay.open')).length) {
            document.body.style.overflow = '';
        }
    }

    function validateImageFile(file, label) {
        if (!file) return (label || 'Photo') + ' is required.';
        if (!ALLOWED_IMAGE_TYPES.includes(String(file.type || '').toLowerCase()) && !/\.(jpe?g|png|webp)$/i.test(file.name || '')) {
            return (label || 'Photo') + ' must be a JPG or PNG image.';
        }
        if (file.size > MAX_IMAGE_BYTES) {
            return (label || 'Photo') + ' must be 5 MB or below.';
        }
        return null;
    }

    function previewResidentImage(input, previewId) {
        var preview = $(previewId);
        if (!preview) return;
        preview.innerHTML = '';

        if (!input.files || !input.files[0]) {
            preview.style.display = 'none';
            return;
        }

        var file = input.files[0];
        var sizeError = validateImageFile(file, 'Photo');
        if (sizeError) {
            input.value = '';
            preview.style.display = 'none';
            if (typeof showSuccessModal === 'function') {
                showSuccessModal('Validation Error', sizeError, true);
            }
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
    }

    function generateComplaintId() {
        var year = new Date().getFullYear();
        var random = Math.floor(Math.random() * 1000);
        return 'COMP-' + year + '-' + String(random).padStart(3, '0');
    }

    function toggleComplaintTypeOtherField() {
        var type = ($('complaintType') || {}).value;
        var group = $('complaintTypeOtherGroup');
        var input = $('complaintTypeOther');
        if (!group || !input) return;
        var showOther = type === 'Other';
        group.hidden = !showOther;
        input.required = showOther;
        if (!showOther) {
            input.value = '';
        }
    }

    function openComplaintModal() {
        closeTipModal();
        openModal('complaintModal');
        var first = $('complaintDate');
        if (first) setTimeout(function () { first.focus(); }, 120);
    }

    function closeComplaintModal() {
        var form = $('complaintForm');
        if (form) form.reset();
        toggleComplaintTypeOtherField();
        var btn = $('complaintSubmitBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Submit Complaint';
        }
        closeModal('complaintModal');
    }

    function openTipModal() {
        closeComplaintModal();
        openModal('tipModal');
        var first = $('tipLocation');
        if (first) setTimeout(function () { first.focus(); }, 120);
    }

    function closeTipModal() {
        var form = $('tipForm');
        if (form) form.reset();
        var preview = $('tipPhotoPreview');
        if (preview) {
            preview.style.display = 'none';
            preview.innerHTML = '';
        }
        var btn = $('tipSubmitBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Submit Tip';
        }
        closeModal('tipModal');
    }

    function submitResidentComplaint(event) {
        event.preventDefault();

        var complaintType = $('complaintType').value;
        var complaintTypeOther = $('complaintTypeOther').value.trim();
        var submitBtn = $('complaintSubmitBtn');

        if (complaintType === 'Other' && complaintTypeOther === '') {
            showSuccessModal('Validation Error', 'Please specify the complaint type when selecting Other.', true);
            $('complaintTypeOther').focus();
            return;
        }

        var complainantContactError = global.AlertaraFormEnhancements.validateContactInput($('complainantContact'), 'Complainant contact number');
        if (complainantContactError) {
            showSuccessModal('Validation Error', complainantContactError, true);
            return;
        }
        var defendantContactError = global.AlertaraFormEnhancements.validateContactInput($('defendantContact'), 'Defendant contact number');
        if (defendantContactError) {
            showSuccessModal('Validation Error', defendantContactError, true);
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }

        var complaintId = generateComplaintId();
        fetch('api/complaints.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                complaint_id: complaintId,
                complainant_name: $('complainantName').value.trim(),
                contact_number: $('complainantContact').value.trim(),
                address: $('complainantAddress').value.trim(),
                incident_date: $('complaintDate').value,
                incident_time: $('complaintTime').value,
                defendant_name: $('defendantName').value.trim(),
                defendant_address: $('defendantAddress').value.trim(),
                defendant_contact_number: $('defendantContact').value.trim(),
                complaint_type: complaintType,
                complaint_type_other: complaintType === 'Other' ? complaintTypeOther : '',
                description: $('complaintDescription').value.trim(),
                status: 'Pending',
                assigned_to: 'Pending Assignment',
                notes: 'Complaint submitted and awaiting review.'
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (!result.success) {
                    showSuccessModal('Error', result.message || 'Failed to submit complaint.', true);
                    return;
                }
                closeComplaintModal();
                showSuccessModal(
                    'Complaint Submitted',
                    'Your complaint ID is ' + complaintId + '. Please keep this ID for tracking and follow-up.',
                    false
                );
            })
            .catch(function (err) {
                console.error('Error submitting complaint:', err);
                showSuccessModal('Error', 'Unable to submit complaint. Please try again.', true);
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Complaint';
                }
            });
    }

    function submitResidentTip(event) {
        event.preventDefault();

        var location = $('tipLocation').value.trim();
        var description = $('tipDescription').value.trim();
        var photoFile = $('tipPhoto').files[0];
        var submitBtn = $('tipSubmitBtn');

        if (!location || !description || !photoFile) {
            showSuccessModal('Validation Error', 'Please fill in all required fields including the photo.', true);
            return;
        }

        var photoError = validateImageFile(photoFile, 'Photo');
        if (photoError) {
            showSuccessModal('Validation Error', photoError, true);
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            fetch('api/tips.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    location: location,
                    description: description,
                    photo: e.target.result
                })
            })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    if (!result.success) {
                        showSuccessModal('Error', result.message || 'Failed to submit tip.', true);
                        return;
                    }
                    var tipId = (result.data && result.data.tip_id) ? result.data.tip_id : '';
                    closeTipModal();
                    showSuccessModal(
                        'Tip Submitted',
                        tipId
                            ? ('Your tip ID is ' + tipId + '. Your tip has been received and will be reviewed.')
                            : 'Your tip has been received and will be reviewed.',
                        false
                    );
                })
                .catch(function (err) {
                    console.error('Error submitting tip:', err);
                    showSuccessModal('Error', 'Unable to submit tip. Please try again.', true);
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Tip';
                    }
                });
        };
        reader.onerror = function () {
            showSuccessModal('Error', 'Unable to read the photo. Please try another image.', true);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Tip';
            }
        };
        reader.readAsDataURL(photoFile);
    }

    window.addEventListener('click', function (event) {
        if (event.target === $('complaintModal')) closeComplaintModal();
        if (event.target === $('tipModal')) closeTipModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeComplaintModal();
            closeTipModal();
        }
    });

    global.openComplaintModal = openComplaintModal;
    global.closeComplaintModal = closeComplaintModal;
    global.openTipModal = openTipModal;
    global.closeTipModal = closeTipModal;
    global.toggleComplaintTypeOtherField = toggleComplaintTypeOtherField;
    global.previewResidentImage = previewResidentImage;
    global.submitResidentComplaint = submitResidentComplaint;
    global.submitResidentTip = submitResidentTip;
})(window);
