<?php
session_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policing and Surveillance - Public Entry</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        :root { --radius: 12px; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        /* Page Container */
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        /* Action Buttons Section */
        .action-buttons-section {
            display: flex;
            gap: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin: 3rem 0;
        }
        .action-buttons-section .btn {
            flex: 1;
            min-width: 220px;
            max-width: 260px;
            height: 220px;
            text-align: center;
            justify-content: center;
            flex-direction: column;
            padding: 1.2rem 1.25rem;
            font-size: 0.95rem;
            background: #ffffff;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: var(--radius);
            box-shadow: 0 4px 12px rgba(76, 138, 137, 0.15);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .action-buttons-section .btn i {
            margin: 0 0 0.65rem 0 !important;
            font-size: 3.5rem;
            color: var(--primary-color);
        }
        .action-buttons-section .btn span {
            color: var(--text-color);
            font-weight: 500;
        }
        .action-buttons-section .btn:hover {
            background: var(--primary-color);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 138, 137, 0.3);
        }
        .action-buttons-section .btn:hover i,
        .action-buttons-section .btn:hover span {
            color: #ffffff;
        }
        /* Inline Modals (not overlays) */
        .modal {
            display: none;
            margin: 2rem 0;
            padding: 0;
        }
        .modal.active {
            display: block;
        }
        .modal-content {
            background: linear-gradient(145deg, var(--tertiary-color), var(--secondary-color));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5);
            padding: clamp(2.5rem, 4vw, 3.5rem);
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
        }
        .modal input:focus, .modal textarea:focus, .modal select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.25);
        }
        .modal .close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .modal-form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 0.25rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .modal-form-actions button {
            flex: 1;
            min-height: 52px;
            padding: 0.875rem 1.5rem;
            border-radius: var(--radius);
            font: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.28);
        }

        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .modal-btn-submit {
            background: var(--primary-color);
            color: #fff;
            border: 1px solid var(--primary-color);
            box-shadow: 0 4px 14px rgba(76, 138, 137, 0.35);
        }

        .modal-btn-submit:hover {
            background: #5a9e9d;
            border-color: #5a9e9d;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(76, 138, 137, 0.45);
        }
        
        /* Success Modal - Still overlay for better UX */
        .success-modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .success-modal.active {
            display: flex;
        }

        .success-modal-content {
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .success-modal-btn {
            min-width: 160px;
            padding: 0.875rem 2.5rem;
            border: none;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--primary-color), #3d7271);
            color: #fff;
            font: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(76, 138, 137, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .success-modal-btn:hover {
            background: linear-gradient(135deg, #5a9e9d, var(--primary-color));
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(76, 138, 137, 0.45);
        }

        .success-modal-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(76, 138, 137, 0.3);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 768px) {
            .action-buttons-section {
                flex-direction: column;
                gap: 1.5rem;
            }
            .action-buttons-section .btn {
                min-width: 100%;
                max-width: 100%;
            }
            .page-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Action Buttons Section -->
        <div class="action-buttons-section">
            <button class="btn btn-secondary" onclick="openTipModal()" type="button">
                <i class="fas fa-shield-alt"></i>
                <span>Magsumite ng reklamo nang palihim. Pindutin ito</span>
            </button>
            <button class="btn btn-secondary" onclick="openMemberApplicationModal()" type="button">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Mag-apply bilang Neighborhood Watch Member</span>
            </button>
        </div>

        <!-- Tip Submission Modal - Inline -->
        <div id="tipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Submit Anonymous Tip</h2>
                <span class="close" onclick="closeTipModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="tipForm" onsubmit="submitTip(event)" autocomplete="off" style="display: grid; gap: 1.75rem;" enctype="multipart/form-data">
                <div class="field">
                    <label for="tipLocation" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Location *</label>
                    <input id="tipLocation" name="location" type="text" placeholder="Enter location where the incident occurred" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="tipPhoto" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Photo *</label>
                    <input id="tipPhoto" name="photo" type="file" accept="image/*" required onchange="previewTipPhoto(this)" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="tipPhotoPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="tipDescription" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Tip Description *</label>
                    <textarea id="tipDescription" name="description" placeholder="Describe the incident or concern in detail" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 120px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="modal-btn-cancel" onclick="closeTipModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Tip
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Message Modal - Still overlay -->
    <div id="successModal" class="success-modal">
        <div class="modal-content" style="background: linear-gradient(145deg, #10b981, #059669); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; box-shadow: 0 20px 50px -25px rgba(0, 0, 0, 0.5); padding: 2.5rem; max-width: 500px; width: 90%; text-align: center; animation: slideIn 0.3s ease-out;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; margin: 0 auto; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; animation: scaleIn 0.3s ease-out;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #fff;"></i>
                </div>
            </div>
            <h2 style="margin: 0 0 1rem 0; color: #fff; font-size: 1.75rem; font-weight: 600;">Success!</h2>
            <p id="successMessage" style="margin: 0 0 2rem 0; color: rgba(255, 255, 255, 0.95); font-size: 1.1rem; line-height: 1.6;"></p>
            <button onclick="closeSuccessModal()" style="padding: 0.875rem 2rem; background: rgba(255, 255, 255, 0.2); color: #fff; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; width: 100%;">OK</button>
        </div>
    </div>

        <!-- Neighborhood Watch Application Modal - Inline -->
        <div id="memberApplicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Neighborhood Watch Application</h2>
                <span class="close" onclick="closeMemberApplicationModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="memberApplicationForm" onsubmit="submitMemberApplication(event)" autocomplete="off" style="display: grid; gap: 1.75rem;">
                <div class="field">
                    <label for="memberName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Full Name *</label>
                    <input id="memberName" name="name" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Contact Number *</label>
                    <input id="memberContact" name="contact" type="tel" class="contact-number-input" placeholder="" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberEmail" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Email Address *</label>
                    <input id="memberEmail" name="email" type="email" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberAddress" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Home Address *</label>
                    <input id="memberAddress" name="address" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberEmergencyName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Full Name *</label>
                    <input id="memberEmergencyName" name="emergencyName" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberEmergencyContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Number *</label>
                    <input id="memberEmergencyContact" name="emergencyContact" type="tel" class="contact-number-input" placeholder="" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="memberPhoto" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Neighborhood Watch Member Photo *</label>
                    <input id="memberPhoto" name="photo" type="file" accept="image/*" required onchange="previewMemberImage(this, 'memberPhotoPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="memberPhotoPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="memberPhotoId" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Photo of Valid ID *</label>
                    <input id="memberPhotoId" name="photoId" type="file" accept="image/*" required onchange="previewMemberImage(this, 'memberPhotoIdPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="memberPhotoIdPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="modal-btn-cancel" onclick="closeMemberApplicationModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    </div> <!-- End page-container -->
    
    <!-- Registration Success Modal - Still overlay -->
    <div id="registrationSuccessModal" class="success-modal">
        <div class="success-modal-content" style="background: linear-gradient(145deg, #ffffff, #f8fafc); border-radius: var(--radius); padding: 3rem clamp(2rem, 4vw, 3rem); max-width: 520px; width: 90%; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4); text-align: center; position: relative; animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div class="success-icon-wrapper" style="width: 100px; height: 100px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: #ffffff; box-shadow: 0 10px 30px -10px rgba(16, 185, 129, 0.5);">
                <i class="fas fa-check"></i>
            </div>
            <h2 style="color: var(--tertiary-color); margin: 0 0 1rem 0; font-size: 1.75rem; font-weight: 700;">Application Submitted!</h2>
            <p style="color: var(--text-secondary); margin: 0 0 1.5rem 0; font-size: 1.05rem; line-height: 1.6;">Your neighborhood watch membership application has been submitted and is pending admin review. Please proceed to the barangay hall for further instructions once approved.</p>
            <div class="success-modal-actions" style="display: flex; justify-content: center; margin-top: 2rem;">
                <button type="button" class="success-modal-btn" onclick="closeRegistrationSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <script src="js/form-contact-validation.js"></script>
    <script>
        function openTipModal() {
            document.getElementById('memberApplicationModal').classList.remove('active');
            document.getElementById('tipModal').classList.add('active');
            document.getElementById('tipModal').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeTipModal() {
            document.getElementById('tipModal').classList.remove('active');
            document.getElementById('tipForm').reset();
            const preview = document.getElementById('tipPhotoPreview');
            if (preview) {
                preview.style.display = 'none';
                preview.innerHTML = '';
            }
        }

        function showSuccessModal(title, message, isError = false) {
            const modal = document.getElementById('successModal');
            const titleElement = modal.querySelector('h2');
            const messageElement = document.getElementById('successMessage');
            const iconElement = modal.querySelector('i');
            const modalContent = modal.querySelector('.modal-content');
            
            titleElement.textContent = title;
            messageElement.innerHTML = message;
            
            if (isError) {
                modalContent.style.background = 'linear-gradient(145deg, #ef4444, #dc2626)';
                iconElement.className = 'fas fa-exclamation-circle';
            } else {
                modalContent.style.background = 'linear-gradient(145deg, #10b981, #059669)';
                iconElement.className = 'fas fa-check-circle';
            }
            
            modal.classList.add('active');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function openMemberApplicationModal() {
            document.getElementById('tipModal').classList.remove('active');
            document.getElementById('memberApplicationModal').classList.add('active');
            document.getElementById('memberApplicationModal').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeMemberApplicationModal() {
            document.getElementById('memberApplicationModal').classList.remove('active');
            document.getElementById('memberApplicationForm').reset();
            document.getElementById('memberPhotoPreview').style.display = 'none';
            document.getElementById('memberPhotoIdPreview').style.display = 'none';
            document.getElementById('memberPhotoPreview').innerHTML = '';
            document.getElementById('memberPhotoIdPreview').innerHTML = '';
        }

        function previewTipPhoto(input) {
            const preview = document.getElementById('tipPhotoPreview');
            if (!preview) return;
            
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.style.cssText = 'max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid rgba(255, 255, 255, 0.2); object-fit: cover; cursor: pointer; transition: transform 0.2s ease;';
                    img.alt = file.name;
                    img.onmouseover = function() { this.style.transform = 'scale(1.02)'; };
                    img.onmouseout = function() { this.style.transform = 'scale(1)'; };
                    img.onclick = function() { viewPhoto(img.src); };
                    
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    preview.appendChild(img);
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            } else {
                preview.style.display = 'none';
            }
        }

        function submitTip(event) {
            event.preventDefault();
            
            const location = document.getElementById('tipLocation').value.trim();
            const description = document.getElementById('tipDescription').value.trim();
            const photoFile = document.getElementById('tipPhoto').files[0];
            
            if (!location || !description || !photoFile) {
                showSuccessModal('Validation Error', 'Please fill in all required fields including the photo.', true);
                return;
            }
            
            // Read photo as base64
            const reader = new FileReader();
            reader.onload = function(e) {
                const photoData = e.target.result;
                
                fetch('api/tips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        location: location,
                        description: description,
                        photo: photoData
                    })
                })
                .then(res => res.json())
                .then(result => {
                    if (!result.success) {
                        showSuccessModal('Error', result.message || 'Failed to submit tip. Please try again.', true);
                        return;
                    }
                    
                    const message = 'Your tip ID is: <strong style="font-size: 1.2em; color: #fff;">' + result.data.tip_id + '</strong><br><br>Your tip has been received and will be reviewed.';
                    showSuccessModal('Tip Submitted Successfully!', message, false);
                    document.getElementById('tipForm').reset();
                    document.getElementById('tipPhotoPreview').style.display = 'none';
                    document.getElementById('tipPhotoPreview').innerHTML = '';
                    closeTipModal();
                })
                .catch(err => {
                    console.error('Error submitting tip:', err);
                    showSuccessModal('Error', 'Error submitting tip. Please try again.', true);
                });
            };
            reader.readAsDataURL(photoFile);
        }

        function previewMemberImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview) return;
            
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.style.cssText = 'max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid rgba(255, 255, 255, 0.2); object-fit: cover; cursor: pointer; transition: transform 0.2s ease;';
                    img.alt = file.name;
                    img.onmouseover = function() { this.style.transform = 'scale(1.02)'; };
                    img.onmouseout = function() { this.style.transform = 'scale(1)'; };
                    img.onclick = function() { viewPhoto(img.src); };
                    
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    preview.appendChild(img);
                } else {
                    const label = document.createElement('div');
                    label.textContent = file.name;
                    label.style.cssText = 'color: #f8fafc; padding: 0.5rem; background: rgba(255, 255, 255, 0.05); border-radius: 6px;';
                    preview.appendChild(label);
                }
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function compressImageFile(file, maxWidth = 1280, quality = 0.82) {
            return new Promise((resolve, reject) => {
                if (!file || !file.type.startsWith('image/')) {
                    reject(new Error('Invalid image file'));
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        let width = img.width;
                        let height = img.height;

                        if (width > maxWidth) {
                            height = Math.round(height * (maxWidth / width));
                            width = maxWidth;
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.onerror = function() {
                        reject(new Error('Failed to load image'));
                    };
                    img.src = e.target.result;
                };
                reader.onerror = function() {
                    reject(new Error('Failed to read image'));
                };
                reader.readAsDataURL(file);
            });
        }

        function submitMemberApplication(event) {
            event.preventDefault();
            
            const name = document.getElementById('memberName').value.trim();
            const contact = document.getElementById('memberContact').value.trim();
            const email = document.getElementById('memberEmail').value.trim();
            const address = document.getElementById('memberAddress').value.trim();
            const emergencyName = document.getElementById('memberEmergencyName').value.trim();
            const emergencyContact = document.getElementById('memberEmergencyContact').value.trim();
            const photoFile = document.getElementById('memberPhoto').files[0];
            const photoIdFile = document.getElementById('memberPhotoId').files[0];
            
            if (!name || !contact || !email || !address || !emergencyName || !emergencyContact || !photoFile || !photoIdFile) {
                showSuccessModal('Validation Error', 'Please fill in all required fields.', true);
                return;
            }

            const contactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('memberContact'), 'Contact number');
            if (contactError) {
                showSuccessModal('Validation Error', contactError, true);
                return;
            }
            const emergencyContactError = AlertaraFormEnhancements.validateContactInput(document.getElementById('memberEmergencyContact'), 'Emergency contact number');
            if (emergencyContactError) {
                showSuccessModal('Validation Error', emergencyContactError, true);
                return;
            }

            Promise.all([
                compressImageFile(photoFile),
                compressImageFile(photoIdFile)
            ])
            .then(function(results) {
                const photoSrc = results[0];
                const photoIdSrc = results[1];

                const formData = {
                    action: 'create',
                    name: name,
                    contact: contact,
                    email: email,
                    address: address,
                    status: 'Pending',
                    photo: photoSrc,
                    photo_id: photoIdSrc,
                    emergency_contact_name: emergencyName,
                    emergency_contact_number: emergencyContact
                };

                return fetch('api/nw_members.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
            })
            .then(function(res) { return res.json(); })
            .then(function(result) {
                if (!result.success) {
                    showSuccessModal('Error', result.message || 'Failed to submit application. Please try again.', true);
                    return;
                }

                closeMemberApplicationModal();
                setTimeout(function() {
                    showRegistrationSuccessModal();
                }, 300);
            })
            .catch(function(err) {
                console.error('Error submitting neighborhood watch application:', err);
                showSuccessModal('Error', 'Unable to process photos. Please use smaller JPG or PNG images and try again.', true);
            });
        }

        function showRegistrationSuccessModal() {
            document.getElementById('registrationSuccessModal').classList.add('active');
        }

        function closeRegistrationSuccessModal() {
            document.getElementById('registrationSuccessModal').classList.remove('active');
        }

        function viewPhoto(src) {
            const modal = document.createElement('div');
            modal.style.cssText = 'position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); display: flex; align-items: center; justify-content: center;';
            modal.onclick = function() { document.body.removeChild(modal); };
            
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);';
            img.onclick = function(e) { e.stopPropagation(); };
            
            modal.appendChild(img);
            document.body.appendChild(modal);
        }

        // Close success modals when clicking outside (only for overlay modals)
        window.onclick = function(event) {
            const successModal = document.getElementById('registrationSuccessModal');
            if (event.target === successModal) {
                closeRegistrationSuccessModal();
            }
            const tipSuccessModal = document.getElementById('successModal');
            if (event.target === tipSuccessModal && !tipSuccessModal.querySelector('.modal-content').contains(event.target)) {
                closeSuccessModal();
            }
        }
    </script>
</body>
</html>
