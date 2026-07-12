
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            initializeTipData();
        });
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
            } else {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
            localStorage.setItem('sidebarCollapsed', !isCollapsed);
        }
        function toggleModule(element) {
            const sidebar = document.getElementById('sidebar');
            const module = element.closest('.nav-module');
            const isActive = module.classList.contains('active');
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
                document.querySelectorAll('.nav-module').forEach(m => { m.classList.remove('active'); });
                module.classList.add('active');
                const firstSubmodule = module.querySelector('.nav-submodule');
                if (firstSubmodule && firstSubmodule.href && firstSubmodule.href !== '#') {
                    window.location.href = firstSubmodule.href;
                }
                return;
            }
            document.querySelectorAll('.nav-module').forEach(m => { m.classList.remove('active'); });
            if (!isActive) { module.classList.add('active'); }
        }
        function filterTips() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tipsTableBody');
            const rows = table.getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent || row.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
        // Tip data storage (loaded from database)
        let tipData = {};
        
        // Load tips from database
        async function loadTips() {
            try {
                const response = await fetch('api/tips.php');
                const result = await response.json();
                
                if (!result.success) {
                    console.error(result.message || 'Failed to load tips');
                    return;
                }
                
                const tips = result.data || [];
                const tbody = document.getElementById('tipsTableBody');
                tbody.innerHTML = '';
                
                // Store tips by id for easy lookup
                tipData = {};
                tips.forEach(tip => {
                    tipData[tip.id] = tip;
                });
                
                // Populate table
                tips.forEach(tip => {
                    addTipTableRow(tip.id);
                });
            } catch (e) {
                console.error('Error loading tips:', e);
            }
        }
        
        function initializeTipData() {
            loadTips();
        }
        
        function getOutcomeBadgeClass(outcome) {
            switch (outcome) {
                case 'Under Investigation':
                    return 'outcome-investigating';
                case 'Investigation Successful':
                    return 'outcome-success';
                case 'Arrest Made':
                    return 'outcome-arrest';
                case 'Unfounded / No Action':
                    return 'outcome-unfounded';
                default:
                    return 'outcome-none';
            }
        }

        function addTipTableRow(id) {
            const tip = tipData[id];
            if (!tip) return;
            
            const tableBody = document.getElementById('tipsTableBody');
            const row = document.createElement('tr');
            row.setAttribute('data-tip-id', id);
            
            const statusClass = tip.status === 'Reviewed' ? 'status-reviewed' : 'status-under-review';
            const statusText = tip.status || 'Under Review';
            const outcomeText = tip.outcome || 'No Outcome Yet';
            const outcomeClass = getOutcomeBadgeClass(outcomeText);
            
            // Format timestamp
            const timestamp = tip.submitted_at ? new Date(tip.submitted_at).toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).replace(',', '') : '';
            
            // Photo thumbnail
            let photoCell = '<div class="tip-photo-placeholder">No Photo</div>';
            if (tip.photo_data) {
                const thumbnailId = 'tip-thumbnail-' + id;
                photoCell = `<img id="${thumbnailId}" src="${tip.photo_data}" alt="Tip Photo" class="tip-photo-thumbnail" data-photo-src="${tip.photo_data.replace(/"/g, '&quot;')}">`;
            }
            
            row.innerHTML = `
                <td>${tip.tip_id || ''}</td>
                <td>${timestamp}</td>
                <td>${tip.location || ''}</td>
                <td>${photoCell}</td>
                <td>${tip.description || ''}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td><span class="outcome-badge ${outcomeClass}">${outcomeText}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewTip('${id}')">View</button>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
            
            // Add click event listener to thumbnail if it exists
            if (tip.photo_data) {
                const thumbnailId = 'tip-thumbnail-' + id;
                setTimeout(() => {
                    const thumbnailElement = document.getElementById(thumbnailId);
                    if (thumbnailElement) {
                        thumbnailElement.addEventListener('click', function() {
                            viewPhotoFull(tip.photo_data);
                        });
                    }
                }, 100);
            }
        }

        let currentTipId = null;

        function viewTip(id) {
            const tip = tipData[id];
            if (!tip) {
                alert('Tip not found');
                return;
            }
            
            currentTipId = id;
            
            // Format timestamp
            const timestamp = tip.submitted_at ? new Date(tip.submitted_at).toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).replace(',', '') : '';
            
            // Photo display
            let photoHtml = '';
            if (tip.photo_data) {
                // Use data attribute to store photo source for better handling of base64 data
                const photoId = 'tip-photo-' + id;
                photoHtml = `<p><strong>Photo:</strong></p><img id="${photoId}" src="${tip.photo_data}" alt="Tip Photo" class="tip-photo-full" data-photo-src="${tip.photo_data.replace(/"/g, '&quot;')}" style="cursor: pointer;">`;
            }
            
            const content = `
                <p><strong>Tip ID:</strong> ${tip.tip_id || ''}</p>
                <p><strong>Timestamp:</strong> ${timestamp}</p>
                <p><strong>Location:</strong> ${tip.location || ''}</p>
                <p><strong>Tip Description:</strong><br>${tip.description || ''}</p>
                ${photoHtml}
            `;
            
            document.getElementById('viewTipContent').innerHTML = content;
            
            // Add click event listener to photo if it exists
            if (tip.photo_data) {
                const photoId = 'tip-photo-' + id;
                setTimeout(() => {
                    const photoElement = document.getElementById(photoId);
                    if (photoElement) {
                        photoElement.addEventListener('click', function() {
                            viewPhotoFull(tip.photo_data);
                        });
                    }
                }, 100);
            }
            document.getElementById('tipStatus').value = tip.status || 'Under Review';
            document.getElementById('tipOutcome').value = tip.outcome || 'No Outcome Yet';
            
            // Show Action button only if status is Reviewed
            const actionButton = document.getElementById('actionButton');
            if (tip.status === 'Reviewed') {
                actionButton.style.display = 'inline-block';
            } else {
                actionButton.style.display = 'none';
            }
            
            document.getElementById('viewTipModal').style.display = 'block';
        }

        function closeViewTipModal() {
            document.getElementById('viewTipModal').style.display = 'none';
            currentTipId = null;
        }

        function updateTipStatus() {
            if (!currentTipId) return;
            
            const status = document.getElementById('tipStatus').value;
            const tip = tipData[currentTipId];
            if (!tip) return;
            
            // Update in database
            fetch('api/tips.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    id: parseInt(currentTipId),
                    status: status,
                    outcome: tip.outcome || 'No Outcome Yet'
                })
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) {
                    alert(result.message || 'Failed to update tip status.');
                    return;
                }
                
                // Update local data
                tipData[currentTipId].status = status;
                
                // Update table row
                const row = document.querySelector(`tr[data-tip-id="${currentTipId}"]`);
                if (row) {
                    const cells = row.querySelectorAll('td');
                    const statusClass = status === 'Reviewed' ? 'status-reviewed' : 'status-under-review';
                    cells[5].innerHTML = `<span class="status-badge ${statusClass}">${status}</span>`;
                }
                
                // Show/hide Action button based on status
                const actionButton = document.getElementById('actionButton');
                if (status === 'Reviewed') {
                    actionButton.style.display = 'inline-block';
                } else {
                    actionButton.style.display = 'none';
                }
            })
            .catch(err => {
                console.error('Error updating tip status:', err);
                alert('Error updating tip status. Please try again.');
            });
        }

        function updateTipOutcome() {
            if (!currentTipId) return;
            
            const outcome = document.getElementById('tipOutcome').value;
            const tip = tipData[currentTipId];
            if (!tip) return;
            
            // Update in database
            fetch('api/tips.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    id: parseInt(currentTipId),
                    status: tip.status || 'Under Review',
                    outcome: outcome
                })
            })
            .then(res => res.json())
            .then(result => {
                if (!result.success) {
                    alert(result.message || 'Failed to update tip outcome.');
                    return;
                }
                
                // Update local data
                tipData[currentTipId].outcome = outcome;
                
                // Update table row
                const row = document.querySelector(`tr[data-tip-id="${currentTipId}"]`);
                if (row) {
                    const cells = row.querySelectorAll('td');
                    const outcomeClass = getOutcomeBadgeClass(outcome);
                    cells[6].innerHTML = `<span class="outcome-badge ${outcomeClass}">${outcome}</span>`;
                }
            })
            .catch(err => {
                console.error('Error updating tip outcome:', err);
                alert('Error updating tip outcome. Please try again.');
            });
        }

        function openActionModal() {
            if (!currentTipId) return;
            
            const tip = tipData[currentTipId];
            if (!tip) return;
            
            // Format timestamp
            const timestamp = tip.submitted_at ? new Date(tip.submitted_at).toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).replace(',', '') : '';
            
            // Populate tip details in action modal
            document.getElementById('actionTipContent').innerHTML = `
                <h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--tertiary-color); font-size: 1.1rem;">Tip Details</h3>
                <p style="margin-bottom: 0.75rem;"><strong>Tip ID:</strong> ${tip.tip_id || ''}</p>
                <p style="margin-bottom: 0.75rem;"><strong>Timestamp:</strong> ${timestamp}</p>
                <p style="margin-bottom: 0.75rem;"><strong>Location:</strong> ${tip.location || ''}</p>
                <p style="margin-bottom: 0;"><strong>Description:</strong> ${tip.description || ''}</p>
            `;
            
            // Reset checkboxes
            document.getElementById('sendToGroup1').checked = false;
            document.getElementById('exportWord').checked = false;
            
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeActionModal() {
            document.getElementById('actionModal').style.display = 'none';
        }

        function executeActions() {
            if (!currentTipId) return;
            
            const tip = tipData[currentTipId];
            const sendToGroup1 = document.getElementById('sendToGroup1').checked;
            const exportWord = document.getElementById('exportWord').checked;
            
            if (!sendToGroup1 && !exportWord) {
                alert('Please select at least one action.');
                return;
            }
            
            const tipDataToSend = {
                tipId: tip.tipId,
                timestamp: tip.timestamp,
                location: tip.location,
                description: tip.description
            };
            
            let actionsCompleted = 0;
            let totalActions = 0;
            
            if (sendToGroup1) {
                totalActions++;
                fetch('api/send_to_group1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(tipDataToSend)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        actionsCompleted++;
                        checkAllActionsComplete();
                    } else {
                        console.error('Error sending to Incident Logging and Classification:', data.message);
                        checkAllActionsComplete();
                    }
                })
                .catch(error => {
                    console.error('Error sending to Incident Logging and Classification:', error);
                    checkAllActionsComplete();
                });
            }
            
            if (exportWord) {
                totalActions++;
                exportTipToWord(tip).then(() => {
                    actionsCompleted++;
                    checkAllActionsComplete();
                }).catch(error => {
                    console.error('Error exporting to Word:', error);
                    checkAllActionsComplete();
                });
            }
            
            function checkAllActionsComplete() {
                if (actionsCompleted >= totalActions && totalActions > 0) {
                    let message = 'Actions completed:\n';
                    if (sendToGroup1) message += '- Sent to Incident Logging and Classification\n';
                    if (exportWord) message += '- Exported to Word document\n';
                    alert(message);
                    closeActionModal();
                }
            }
        }

        async function exportTipToWord(tip) {
            try {
                if (typeof JSZip === 'undefined') {
                    alert('Export library not loaded. Please refresh the page.');
                    return;
                }

                const zip = new JSZip();

                const escapeXml = (text) => {
                    if (!text) return '';
                    return String(text)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&apos;');
                };

                const contentTypes = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n' +
'    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n' +
'    <Default Extension="xml" ContentType="application/xml"/>\n' +
'    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>\n' +
'    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>\n' +
'</Types>';

                const documentXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">\n' +
'    <w:body>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="center"/>\n' +
'                <w:spacing w:after="400"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:rPr>\n' +
'                    <w:b/>\n' +
'                    <w:sz w:val="32"/>\n' +
'                </w:rPr>\n' +
'                <w:t>TIP REPORT</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="center"/>\n' +
'                <w:spacing w:after="600"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:t>Barangay San Agustin, Quezon City</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Tip ID:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.tipId) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Timestamp:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.timestamp) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Location:</w:t>\n' +
'            </w:r>\n' +
'            <w:r>\n' +
'                <w:t> ' + escapeXml(tip.location) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:spacing w:before="400"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:rPr><w:b/></w:rPr>\n' +
'                <w:t>Tip Description:</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:r>\n' +
'                <w:t>' + escapeXml(tip.description) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'        <w:p>\n' +
'            <w:pPr>\n' +
'                <w:jc w:val="right"/>\n' +
'                <w:spacing w:before="600"/>\n' +
'            </w:pPr>\n' +
'            <w:r>\n' +
'                <w:t>Generated on: ' + escapeXml(new Date().toLocaleString()) + '</w:t>\n' +
'            </w:r>\n' +
'        </w:p>\n' +
'    </w:body>\n' +
'</w:document>';

                const stylesXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">\n' +
'    <w:style w:type="paragraph" w:styleId="Normal">\n' +
'        <w:name w:val="Normal"/>\n' +
'        <w:qFormat/>\n' +
'    </w:style>\n' +
'</w:styles>';

                const rels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>\n' +
'</Relationships>';

                const wordRels = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n' +
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n' +
'    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>\n' +
'</Relationships>';

                zip.file("[Content_Types].xml", contentTypes);
                zip.file("word/document.xml", documentXml);
                zip.file("word/styles.xml", stylesXml);
                zip.file("_rels/.rels", rels);
                zip.file("word/_rels/document.xml.rels", wordRels);

                const blob = await zip.generateAsync({ type: "blob", mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document" });
                const fileName = `tip_report_${tip.tipId}_${tip.timestamp.replace(/[:\s]/g, '_')}.docx`;
                
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            } catch (error) {
                console.error('Error generating DOCX:', error);
                throw error;
            }
        }


        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewTipModal');
            const actionModal = document.getElementById('actionModal');
            
            if (event.target === viewModal) {
                closeViewTipModal();
            }
            if (event.target === actionModal) {
                closeActionModal();
            }
        }

        function viewPhotoFull(photoSrc) {
            if (!photoSrc) {
                alert('No photo available');
                return;
            }
            
            const modal = document.createElement('div');
            modal.id = 'fullscreen-photo-modal';
            modal.style.cssText = 'position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); display: flex; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;';
            
            const img = document.createElement('img');
            img.src = photoSrc;
            img.style.cssText = 'max-width: 95%; max-height: 95%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); object-fit: contain; cursor: zoom-out;';
            img.onclick = function(e) { 
                e.stopPropagation(); 
            };
            img.onerror = function() {
                alert('Failed to load photo');
                document.body.removeChild(modal);
                if (escHandler) document.removeEventListener('keydown', escHandler);
            };
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'position: absolute; top: 20px; right: 30px; background: rgba(255, 255, 255, 0.2); color: #fff; border: none; font-size: 40px; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s ease; z-index: 3001;';
            closeBtn.onmouseover = function() { this.style.background = 'rgba(255, 255, 255, 0.3)'; };
            closeBtn.onmouseout = function() { this.style.background = 'rgba(255, 255, 255, 0.2)'; };
            
            // Add ESC key listener
            const escHandler = function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    const existingModal = document.getElementById('fullscreen-photo-modal');
                    if (existingModal) {
                        document.body.removeChild(existingModal);
                        document.removeEventListener('keydown', escHandler);
                    }
                }
            };
            
            const closeModal = function() {
                document.body.removeChild(modal);
                document.removeEventListener('keydown', escHandler);
            };
            
            modal.onclick = closeModal;
            closeBtn.onclick = function(e) {
                e.stopPropagation();
                closeModal();
            };
            document.addEventListener('keydown', escHandler);
            
            modal.appendChild(closeBtn);
            modal.appendChild(img);
            document.body.appendChild(modal);
        }

        // Date and Time Display
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            const dateStr = now.toLocaleDateString('en-US', dateOptions);
            const timeStr = now.toLocaleTimeString('en-US', timeOptions);
            
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            
            if (dateEl) dateEl.textContent = dateStr;
            if (timeEl) timeEl.textContent = timeStr;
        }
        
        // Update date/time immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Notification System
        let notificationDropdown = null;
        let notificationBadge = null;
        let notificationList = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            notificationDropdown = document.getElementById('notificationDropdown');
            notificationBadge = document.getElementById('notificationBadge');
            notificationList = document.getElementById('notificationList');
            
            if (notificationDropdown && notificationBadge && notificationList) {
                loadNotifications();
                // Refresh notifications every 30 seconds
                setInterval(loadNotifications, 30000);
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (notificationDropdown && !event.target.closest('.notification-container')) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }
        });
        
        function toggleNotifications() {
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            }
        }
        
        async function loadNotifications() {
            try {
                // Sync activities first
                await fetch('api/notifications.php?action=sync');
                
                // Then load notifications
                const response = await fetch('api/notifications.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    renderNotifications(data.notifications);
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }
        
        function updateNotificationBadge(count) {
            if (notificationBadge) {
                if (count > 0) {
                    notificationBadge.textContent = count > 99 ? '99+' : count;
                    notificationBadge.classList.add('show');
                } else {
                    notificationBadge.classList.remove('show');
                }
            }
        }
        
        function renderNotifications(notifications) {
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationList.innerHTML = notifications.map(notif => {
                let iconClass, icon;
                if (notif.type === 'complaint' || notif.type === 'incident') {
                    iconClass = 'complaint';
                    icon = 'fa-file-alt';
                } else if (notif.type === 'tip') {
                    iconClass = 'tip';
                    icon = 'fa-comments';
                } else if (notif.type === 'volunteer' || notif.type === 'volunteer_request') {
                    iconClass = 'volunteer';
                    icon = 'fa-handshake';
                } else if (notif.type === 'login') {
                    iconClass = 'login';
                    icon = 'fa-sign-in-alt';
                } else if (notif.type === 'logout') {
                    iconClass = 'logout';
                    icon = 'fa-sign-out-alt';
                } else if (notif.type === 'event' || notif.type === 'event_report' || notif.type === 'patrol') {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                } else {
                    iconClass = 'event';
                    icon = 'fa-bullhorn';
                }
                
                const safeLink = (notif.link || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                return `
                    <div class="notification-item ${notif.is_read ? '' : 'unread'}" 
                         onclick="handleNotificationClick(${notif.id}, '${safeLink}')">
                        <div class="notification-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${notif.time_ago}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function handleNotificationClick(id, link) {
            // Mark as read
            fetch('api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            });
            
            // Remove unread class
            const item = event.currentTarget;
            item.classList.remove('unread');
            
            // Navigate if link exists
            if (link && link !== '') {
                window.location.href = link;
            }
            
            // Reload notifications to update badge
            loadNotifications();
        }
        
        async function markAllAsRead() {
            try {
                await fetch('api/notifications.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                loadNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

    
