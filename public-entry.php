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
            <button class="btn btn-secondary" onclick="openVolunteerModal()" type="button">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Gusto ko mag volunteer</span>
            </button>
        </div>

        <!-- Tip Submission Modal - Inline -->
        <div id="tipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Submit Anonymous Tip</h2>
                <span class="close" onclick="closeTipModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="tipForm" onsubmit="submitTip(event)" style="display: grid; gap: 1.75rem;" enctype="multipart/form-data">
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
                <div class="button-group" style="margin-top: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeTipModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1;">Submit Tip</button>
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

        <!-- Volunteer Registration Modal - Inline -->
        <div id="volunteerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.16);">
                <h2 style="margin: 0; color: #f8fafc; font-size: 1.75rem; font-weight: 600;">Volunteer Registration</h2>
                <span class="close" onclick="closeVolunteerModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.75rem; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s ease; line-height: 1;">&times;</span>
            </div>
            <form id="volunteerForm" onsubmit="submitVolunteer(event)" style="display: grid; gap: 1.75rem;">
                <div class="field">
                    <label for="volunteerName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Full Name *</label>
                    <input id="volunteerName" name="name" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Contact Number *</label>
                    <input id="volunteerContact" name="contact" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerEmail" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Email Address *</label>
                    <input id="volunteerEmail" name="email" type="email" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerAddress" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Home Address *</label>
                    <input id="volunteerAddress" name="address" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerCategory" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Category *</label>
                    <select id="volunteerCategory" name="category" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                        <option value="" style="background: var(--tertiary-color); color: #f8fafc;">Select category</option>
                        <option value="Community Outreach" style="background: var(--tertiary-color); color: #f8fafc;">Community Outreach</option>
                        <option value="Emergency Response" style="background: var(--tertiary-color); color: #f8fafc;">Emergency Response</option>
                        <option value="Event Management" style="background: var(--tertiary-color); color: #f8fafc;">Event Management</option>
                        <option value="Training and Education" style="background: var(--tertiary-color); color: #f8fafc;">Training and Education</option>
                        <option value="Administrative Support" style="background: var(--tertiary-color); color: #f8fafc;">Administrative Support</option>
                    </select>
                </div>
                <div class="field">
                    <label for="volunteerSkills" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Skills *</label>
                    <textarea id="volunteerSkills" name="skills" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 80px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="field">
                    <label for="volunteerAvailability" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Availability *</label>
                    <select id="volunteerAvailability" name="availability" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                        <option value="" style="background: var(--tertiary-color); color: #f8fafc;">Select availability</option>
                        <option value="Weekdays" style="background: var(--tertiary-color); color: #f8fafc;">Weekdays</option>
                        <option value="Weekends" style="background: var(--tertiary-color); color: #f8fafc;">Weekends</option>
                        <option value="Both" style="background: var(--tertiary-color); color: #f8fafc;">Both</option>
                        <option value="Flexible" style="background: var(--tertiary-color); color: #f8fafc;">Flexible</option>
                    </select>
                </div>
                <div class="field">
                    <label for="volunteerEmergencyName" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Full Name *</label>
                    <input id="volunteerEmergencyName" name="emergencyName" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerEmergencyContact" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Emergency Contact Number *</label>
                    <input id="volunteerEmergencyContact" name="emergencyContact" type="text" required style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                </div>
                <div class="field">
                    <label for="volunteerPhoto" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Photo *</label>
                    <input id="volunteerPhoto" name="photo" type="file" accept="image/*" required onchange="previewVolunteerImage(this, 'volunteerPhotoPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerPhotoPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerPhotoId" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Volunteer Valid ID *</label>
                    <input id="volunteerPhotoId" name="photoId" type="file" accept="image/*" required onchange="previewVolunteerImage(this, 'volunteerPhotoIdPreview')" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerPhotoIdPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerCertifications" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Certifications</label>
                    <input id="volunteerCertifications" name="certifications" type="file" accept=".jpeg,.jpg,.png,.pdf" multiple onchange="handleCertificationUpload(this)" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; cursor: pointer;">
                    <div id="volunteerCertificationsPreview" style="margin-top: 0.5rem; display: none;"></div>
                </div>
                <div class="field">
                    <label for="volunteerCertificationsDescription" style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500;">Certification Details</label>
                    <textarea id="volunteerCertificationsDescription" name="certDescription" style="width: 100%; padding: 1.15rem 1.5rem; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: var(--radius); font: inherit; font-size: 1.1rem; color: #f8fafc; background: rgba(255, 255, 255, 0.08); min-height: 80px; resize: vertical; transition: border-color 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box; font-family: inherit;"></textarea>
                </div>
                <div class="button-group" style="margin-top: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeVolunteerModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1;">Submit Registration</button>
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
            <h2 style="color: var(--tertiary-color); margin: 0 0 1rem 0; font-size: 1.75rem; font-weight: 700;">Registration Successful!</h2>
            <p style="color: var(--text-secondary); margin: 0 0 1.5rem 0; font-size: 1.05rem; line-height: 1.6;">Registration submitted successfully! Please proceed to the barangay hall to get your physical ID.</p>
            <div class="success-modal-actions" style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                <button type="button" class="btn" onclick="closeRegistrationSuccessModal()" style="min-width: 140px;">OK</button>
            </div>
        </div>
    </div>

    <script>
        let selectedCertFiles = [];

        function openTipModal() {
            document.getElementById('tipModal').classList.add('active');
            // Scroll to modal
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

        function openVolunteerModal() {
            document.getElementById('volunteerModal').classList.add('active');
            // Scroll to modal
            document.getElementById('volunteerModal').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeVolunteerModal() {
            document.getElementById('volunteerModal').classList.remove('active');
            document.getElementById('volunteerForm').reset();
            document.getElementById('volunteerPhotoPreview').style.display = 'none';
            document.getElementById('volunteerPhotoIdPreview').style.display = 'none';
            document.getElementById('volunteerCertificationsPreview').style.display = 'none';
            document.getElementById('volunteerPhotoPreview').innerHTML = '';
            document.getElementById('volunteerPhotoIdPreview').innerHTML = '';
            document.getElementById('volunteerCertificationsPreview').innerHTML = '';
            selectedCertFiles = [];
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

        function previewVolunteerImage(input, previewId) {
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

        function handleCertificationUpload(input) {
            if (input.files && input.files.length > 0) {
                const existing = new Set(selectedCertFiles.map(f => `${f.name}|${f.size}`));
                Array.from(input.files).forEach(file => {
                    const key = `${file.name}|${file.size}`;
                    if (!existing.has(key)) {
                        selectedCertFiles.push(file);
                        existing.add(key);
                    }
                });
            }
            
            renderCertificationsPreview(input, document.getElementById('volunteerCertificationsPreview'));
        }

        function renderCertificationsPreview(input, preview) {
            preview.innerHTML = '';
            
            if (selectedCertFiles.length > 0) {
                selectedCertFiles.forEach((file, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.08); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); transition: background 0.2s ease;';
                    wrapper.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.12)'; };
                    wrapper.onmouseout = function() { this.style.background = 'rgba(255, 255, 255, 0.08)'; };
                    
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid rgba(255, 255, 255, 0.2); cursor: pointer; transition: transform 0.2s ease;';
                        img.alt = file.name;
                        img.onmouseover = function() { this.style.transform = 'scale(1.1)'; };
                        img.onmouseout = function() { this.style.transform = 'scale(1)'; };
                        img.onclick = function() { viewPhoto(img.src); };
                        
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                        wrapper.appendChild(img);
                    } else {
                        const fileIcon = document.createElement('div');
                        fileIcon.style.cssText = 'width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border-radius: 6px; border: 2px solid rgba(255, 255, 255, 0.2);';
                        fileIcon.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 1.5rem; color: rgba(255, 255, 255, 0.8);"></i>';
                        wrapper.appendChild(fileIcon);
                    }
                    
                    const label = document.createElement('div');
                    label.textContent = file.name;
                    label.style.cssText = 'flex: 1; font-size: 0.85rem; color: #f8fafc; padding: 0 0.5rem; word-break: break-word;';
                    wrapper.appendChild(label);
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = 'Remove';
                    removeBtn.style.cssText = 'padding: 0.4rem 0.75rem; font-size: 0.75rem; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.3); cursor: pointer; background: rgba(239, 68, 68, 0.2); color: #f8fafc; transition: all 0.2s ease;';
                    removeBtn.onmouseover = function() { this.style.background = 'rgba(239, 68, 68, 0.4)'; this.style.borderColor = 'rgba(255, 255, 255, 0.5)'; };
                    removeBtn.onmouseout = function() { this.style.background = 'rgba(239, 68, 68, 0.2)'; this.style.borderColor = 'rgba(255, 255, 255, 0.3)'; };
                    removeBtn.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectedCertFiles = selectedCertFiles.filter(f => f !== file);
                        const dt = new DataTransfer();
                        selectedCertFiles.forEach(f => dt.items.add(f));
                        input.files = dt.files;
                        renderCertificationsPreview(input, preview);
                    };
                    wrapper.appendChild(removeBtn);
                    
                    preview.appendChild(wrapper);
                });
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function submitVolunteer(event) {
            event.preventDefault();
            
            const name = document.getElementById('volunteerName').value.trim();
            const contact = document.getElementById('volunteerContact').value.trim();
            const email = document.getElementById('volunteerEmail').value.trim();
            const address = document.getElementById('volunteerAddress').value.trim();
            const category = document.getElementById('volunteerCategory').value;
            const skills = document.getElementById('volunteerSkills').value.trim();
            const availability = document.getElementById('volunteerAvailability').value;
            const emergencyName = document.getElementById('volunteerEmergencyName').value.trim();
            const emergencyContact = document.getElementById('volunteerEmergencyContact').value.trim();
            const photoFile = document.getElementById('volunteerPhoto').files[0];
            const photoIdFile = document.getElementById('volunteerPhotoId').files[0];
            const certDescription = document.getElementById('volunteerCertificationsDescription').value.trim();
            
            if (!name || !contact || !email || !address || !category || !skills || !availability || !emergencyName || !emergencyContact || !photoFile || !photoIdFile) {
                alert('Please fill in all required fields.');
                return;
            }
            
            let certificationsData = [];
            const certPromises = selectedCertFiles.map(file => {
                return new Promise(resolve => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        certificationsData.push({
                            name: file.name,
                            data: e.target.result,
                            type: file.type
                        });
                        resolve();
                    };
                    reader.readAsDataURL(file);
                });
            });
            
            const reader1 = new FileReader();
            reader1.onload = function(e1) {
                const photoSrc = e1.target.result;
                
                const reader2 = new FileReader();
                reader2.onload = function(e2) {
                    const photoIdSrc = e2.target.result;
                    
                    Promise.all(certPromises).then(() => {
                        const formData = {
                            action: 'create',
                            name: name,
                            contact: contact,
                            email: email,
                            address: address,
                            category: category,
                            skills: skills,
                            availability: availability,
                            status: 'Pending',
                            notes: '',
                            photo: photoSrc,
                            photo_id: photoIdSrc,
                            certifications: certificationsData,
                            certifications_description: certDescription,
                            emergency_contact_name: emergencyName,
                            emergency_contact_number: emergencyContact
                        };
                        
                        fetch('api/volunteers.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(formData)
                        })
                        .then(res => res.json())
                        .then(result => {
                            if (!result.success) {
                                alert(result.message || 'Failed to submit registration. Please try again.');
                                return;
                            }
                            
                            closeVolunteerModal();
                            setTimeout(() => {
                                showRegistrationSuccessModal();
                            }, 300);
                        })
                        .catch(err => {
                            console.error('Error submitting volunteer registration:', err);
                            alert('Error submitting registration. Please try again.');
                        });
                    });
                };
                reader2.readAsDataURL(photoIdFile);
            };
            reader1.readAsDataURL(photoFile);
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
