<?php
$autoOpenLogin = false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Resident Portal - AlerTara QC</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<<<<<<< HEAD
    <link rel="stylesheet" href="css/portal-landing.css?v=20260721b">
=======
    <link rel="stylesheet" href="css/portal-landing.css">
>>>>>>> bd0e9e2fcfed13fcdf64eabe653cdae9394a7d69
    <link rel="stylesheet" href="css/mobile-responsive.css">
</head>
<body>
    <div class="progress-bar" id="progressBar" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav">
        <a href="login.php" class="nav-brand">
            <img src="images/logo.svg" alt="AlerTara QC">
            <span class="nav-brand-title">Community Policing and Surveillance</span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
        <ul class="nav-links" id="navLinks">
            <li><a href="#about">About</a></li>
            <li><a href="#complaint">Submit Complaint</a></li>
            <li><a href="#tip">Send Tip</a></li>
            <li><a href="#mission">Mission</a></li>
            <li><a href="#vision">Vision</a></li>
            <li><a href="#values">Values</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav-actions">
            <a class="nav-back" href="login.php"><i class="fas fa-arrow-left"></i> Main page</a>
            <a class="nav-cta" href="#services">Get help</a>
        </div>
    </header>

    <main id="top">
        <section class="hero" aria-label="Hero">
            <div class="hero-bg"></div>
            <div class="hero-scan" aria-hidden="true"></div>
            <div class="hero-radar" aria-hidden="true"><div class="radar-beam"></div></div>
            <div class="hero-content">
                <p class="portal-pill"><i class="fas fa-users"></i> Resident Portal</p>
                <div class="brand-mark" aria-label="AlerTara QC">
                    <img src="images/tara.png" alt="">
                    <span class="ler">ler</span><span class="rest">Tara QC</span>
                </div>
                <h1>Resident Services</h1>
                <p class="lead">Submit a barangay complaint or send an anonymous tip to help keep Barangay San Agustin safer—no account required.</p>
                <div class="hero-ctas">
                    <a class="btn" href="#services">Explore services</a>
                    <a class="btn btn-ghost" href="login.php">Back to main page</a>
                </div>
            </div>
            <a class="scroll-cue" href="#about" aria-label="Scroll to about section">
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </a>
        </section>

        <section class="section about-section" id="about">
            <div class="section-inner">
                <p class="section-label reveal">About this portal</p>
                <h2 class="reveal reveal-delay-1">Built for Barangay San Agustin residents</h2>
                <p class="sub reveal reveal-delay-2">This portal helps residents report concerns and share information that supports community policing in Barangay San Agustin, Novaliches, Quezon City.</p>
                <p class="about-copy reveal reveal-delay-3">
                    Use the services below to file a formal complaint or send an anonymous tip.
                    Both options go to authorized barangay reviewers so the right response can begin.
                </p>
                <ul class="feature-list">
                    <li class="reveal">
                        <strong>Formal complaints</strong>
                        Submit a detailed complaint with your contact information for follow-up.
                    </li>
                    <li class="reveal reveal-delay-1">
                        <strong>Anonymous tips</strong>
                        Share concerns quietly with a location, photo, and description—without revealing who you are.
                    </li>
                    <li class="reveal reveal-delay-2">
                        <strong>Community-first</strong>
                        Every submission helps strengthen awareness and safety across Barangay San Agustin.
                    </li>
                </ul>
            </div>
        </section>

        <section class="section who-section" id="services">
            <div class="section-inner">
                <p class="section-label reveal">Resident services</p>
                <h2 class="reveal reveal-delay-1">How can we help you today?</h2>
                <p class="sub reveal reveal-delay-2">Choose a service below. Each card explains what it is for and how it works, then opens the matching form.</p>
                <div class="services-grid">
                    <article class="service-card reveal" id="complaint">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-file-signature"></i></div>
                        <h3>Submit Complaint</h3>
                        <p>
                            Use this when you need to file a formal barangay complaint about a person or incident.
                            Your details help officers contact you for updates and clarification.
                        </p>
                        <ul class="how-it-works">
                            <li>Open the form and fill in complainant and defendant information.</li>
                            <li>Describe what happened, including date, time, and complaint type.</li>
                            <li>Submit to get a complaint ID for tracking and review.</li>
                        </ul>
                        <button type="button" class="btn" onclick="openComplaintModal()">Open complaint form</button>
                    </article>
                    <article class="service-card reveal reveal-delay-1" id="tip">
                        <div class="role-icon" aria-hidden="true"><i class="fas fa-user-secret"></i></div>
                        <h3>Send Tip Anonymously</h3>
                        <p>
                            Use this when you want to report a concern without sharing your identity.
                            Tips help barangay responders stay ahead of issues in your neighborhood.
                        </p>
                        <ul class="how-it-works">
                            <li>Provide the location where the concern happened.</li>
                            <li>Attach a clear photo and describe what you observed.</li>
                            <li>Submit anonymously and keep the tip ID for reference.</li>
                        </ul>
                        <button type="button" class="btn" onclick="openTipModal()">Open tip form</button>
                    </article>
                </div>
            </div>
        </section>

        <section class="section mvv-section">
            <div class="section-inner mvv-stack">
                <div class="mvv-block reveal" id="mission">
                    <p class="section-label">Mission</p>
                    <h3>Our Mission</h3>
                    <p>To provide a unified, efficient, and responsive emergency management system for Barangay San Agustin that protects lives and property through seamless coordination and real-time information sharing.</p>
                </div>
                <div class="mvv-block reveal" id="vision">
                    <p class="section-label">Vision</p>
                    <h3>Our Vision</h3>
                    <p>To become a model barangay for community policing and surveillance in Novaliches, Quezon City—leveraging technology to create a safer, more resilient Barangay San Agustin through proactive and coordinated public safety initiatives.</p>
                </div>
                <div class="mvv-block reveal" id="values">
                    <p class="section-label">Values</p>
                    <h3>Our Values</h3>
                    <p>Integrity, Excellence, Collaboration, and Innovation guide our commitment to serving the people of Barangay San Agustin, Novaliches, Quezon City with dedication and professionalism in every public safety operation.</p>
                </div>
            </div>
        </section>

        <section class="section contact-section" id="contact">
            <div class="section-inner">
                <p class="section-label reveal">Contact us</p>
                <h2 class="reveal reveal-delay-1">We're here to help</h2>
                <div class="contact-info reveal reveal-delay-1">
                    <p>Reach the AlerTara QC team in Barangay San Agustin for support or questions about resident services.</p>
                    <div>
                        <strong>Email</strong>
                        contactcps@alertaraqc.gov.ph
                    </div>
                    <div>
                        <strong>Address</strong>
                        Barangay San Agustin, Novaliches, Quezon City, Metro Manila Philippines
                    </div>
                    <div>
                        <strong>Operation Hours</strong>
                        24/7
                    </div>
                    <div style="margin-top:1rem;">
                        <a class="btn btn-ghost" href="login.php">Explore the main page</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">Aler<span>Tara</span> QC</div>
            <div class="footer-links">
                <a href="#services">Services</a>
                <a href="login.php">Main page</a>
                <button type="button" onclick="openLegalModal('privacy')">Privacy Policy</button>
                <button type="button" onclick="openLegalModal('terms')">Terms of Service</button>
                <button type="button" onclick="openLegalModal('cookies')">Cookie Policy</button>
                <a href="#contact">Contact</a>
            </div>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> AlerTara QC — Resident Portal for Barangay San Agustin. All rights reserved.</p>
        </div>
    </footer>

    <div class="modal-overlay" id="complaintModal" role="dialog" aria-modal="true" aria-labelledby="complaint-title" style="z-index:2050;">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="complaint-title">Submit Complaint</h2>
                <button type="button" class="modal-close" onclick="closeComplaintModal()" aria-label="Close">&times;</button>
            </div>
            <p class="register-hint">Provide complete details so barangay reviewers can process your complaint.</p>
            <form id="complaintForm" onsubmit="submitResidentComplaint(event)" autocomplete="off">
                <div class="form-row">
                    <div class="field">
                        <label for="complaintDate">Date *</label>
                        <input id="complaintDate" name="complaintDate" type="date" required>
                    </div>
                    <div class="field">
                        <label for="complaintTime">Time *</label>
                        <input id="complaintTime" name="complaintTime" type="time" required>
                    </div>
                </div>
                <div class="field">
                    <label for="complainantName">Complainant's Name *</label>
                    <input id="complainantName" name="complainantName" type="text" required>
                </div>
                <div class="field">
                    <label for="complainantAddress">Complainant's Address *</label>
                    <input id="complainantAddress" name="complainantAddress" type="text" required>
                </div>
                <div class="field">
                    <label for="complainantContact">Complainant's Contact Number *</label>
                    <input id="complainantContact" name="complainantContact" type="tel" class="contact-number-input" placeholder="" required>
                </div>
                <div class="field">
                    <label for="defendantName">Defendant's Name *</label>
                    <input id="defendantName" name="defendantName" type="text" required>
                </div>
                <div class="field">
                    <label for="defendantAddress">Defendant's Address *</label>
                    <input id="defendantAddress" name="defendantAddress" type="text" required>
                </div>
                <div class="field">
                    <label for="defendantContact">Defendant's Contact Number</label>
                    <input id="defendantContact" name="defendantContact" type="tel" class="contact-number-input" placeholder="" data-contact-required="false">
                </div>
                <div class="field">
                    <label for="complaintType">Complaint Type *</label>
                    <select id="complaintType" name="complaintType" required onchange="toggleComplaintTypeOtherField()">
                        <option value="">Select Type</option>
                        <option value="Noise">Noise Complaint</option>
                        <option value="Vandalism">Vandalism</option>
                        <option value="Trespassing">Trespassing</option>
                        <option value="Safety">Safety Concern</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="field" id="complaintTypeOtherGroup" hidden>
                    <label for="complaintTypeOther">Specify Complaint Type *</label>
                    <input id="complaintTypeOther" name="complaintTypeOther" type="text">
                </div>
                <div class="field">
                    <label for="complaintDescription">Description of Complaint *</label>
                    <textarea id="complaintDescription" name="complaintDescription" required></textarea>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeComplaintModal()">Cancel</button>
                    <button type="submit" class="btn" id="complaintSubmitBtn">Submit Complaint</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="tipModal" role="dialog" aria-modal="true" aria-labelledby="tip-title" style="z-index:2050;">
        <div class="modal-panel wide">
            <div class="modal-header">
                <h2 id="tip-title">Send Tip Anonymously</h2>
                <button type="button" class="modal-close" onclick="closeTipModal()" aria-label="Close">&times;</button>
            </div>
            <p class="register-hint">Your identity is not collected. Include a clear photo and enough detail for barangay reviewers to act.</p>
            <form id="tipForm" onsubmit="submitResidentTip(event)" autocomplete="off">
                <div class="field">
                    <label for="tipLocation">Location *</label>
                    <input id="tipLocation" name="location" type="text" required>
                </div>
                <div class="field">
                    <label for="tipPhoto">Photo *</label>
                    <input id="tipPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required onchange="previewResidentImage(this, 'tipPhotoPreview')">
                    <p class="field-hint">JPG or PNG, 10 MB or below.</p>
                    <div id="tipPhotoPreview" class="file-preview"></div>
                </div>
                <div class="field">
                    <label for="tipDescription">Tip Description *</label>
                    <textarea id="tipDescription" name="description" required></textarea>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeTipModal()">Cancel</button>
                    <button type="submit" class="btn" id="tipSubmitBtn">Submit Tip</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/form-contact-validation.js"></script>
    <script src="js/resident-portal.js"></script>

<?php
$forgotApiEndpoint = 'api/forgot-password.php';
$portalHomePath = 'resident-portal.php';
require __DIR__ . '/includes/portal_landing_modals.php';
