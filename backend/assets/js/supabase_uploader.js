/**
 * Book Banko - Direct Client-Side Supabase Storage Uploader
 * Bypasses serverless payload size limits (e.g., Vercel 4.5MB limit) by streaming
 * large files (PDFs, banners, images) directly from the browser to Supabase Cloud Storage.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form[enctype="multipart/form-data"]');
        if (!forms.length) return;

        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                // If direct upload has already completed, allow natural submission
                if (form.dataset.directUploadCompleted === 'true') {
                    return true;
                }

                // Find file input with a selected file
                const fileInputs = form.querySelectorAll('input[type="file"]');
                let targetInput = null;
                let file = null;

                for (let i = 0; i < fileInputs.length; i++) {
                    if (fileInputs[i].files && fileInputs[i].files.length > 0) {
                        targetInput = fileInputs[i];
                        file = fileInputs[i].files[0];
                        break;
                    }
                }

                // If no file selected, proceed with regular form submit
                if (!file) {
                    return true;
                }

                // If Supabase is not configured on window, proceed normally
                if (!window.SUPABASE_CONFIG || !window.SUPABASE_CONFIG.url || !window.SUPABASE_CONFIG.anonKey) {
                    return true;
                }

                const inputName = targetInput.name || '';
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);

                // Supabase Storage limit check (100MB for video, 50MB for PDFs/images)
                const isVideo = ['mp4', 'webm', 'mov'].includes(ext);
                const maxAllowedBytes = isVideo ? 104857600 : 52428800;
                const maxAllowedMB = isVideo ? 100 : 50;

                if (file.size > maxAllowedBytes) {
                    e.preventDefault();
                    alert('File size (' + fileSizeMB + ' MB) exceeds maximum upload limit of ' + maxAllowedMB + ' MB. Please compress or select a smaller file.');
                    return false;
                }

                // Determine bucket
                let bucket = 'pdfs';
                if (inputName.includes('video') || inputName.includes('banner') || inputName.includes('custom_ad') || isVideo) {
                    bucket = 'banners';
                } else if (inputName.includes('product')) {
                    bucket = 'products';
                } else if (inputName.includes('icon')) {
                    bucket = 'icons';
                } else if (['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(ext)) {
                    if (form.action.includes('banner') || form.action.includes('settings') || window.location.href.includes('settings')) bucket = 'banners';
                    else if (form.action.includes('product')) bucket = 'products';
                    else bucket = 'icons';
                }

                e.preventDefault();

                // Generate unique filename matching backend standard
                const uniqueId = 'bb_' + Date.now().toString(16) + '_' + Math.random().toString(16).substring(2, 8) + '.' + ext;
                
                // Show Progress UI
                showUploadModal(file.name, fileSizeMB);

                // Upload directly to Supabase Storage REST API
                const uploadUrl = window.SUPABASE_CONFIG.url.replace(/\/+$/, '') + '/storage/v1/object/' + bucket + '/' + uniqueId;
                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl, true);

                let mimeType = file.type || 'application/octet-stream';
                if (ext === 'pdf') mimeType = 'application/pdf';
                else if (ext === 'jpg' || ext === 'jpeg') mimeType = 'image/jpeg';
                else if (ext === 'png') mimeType = 'image/png';
                else if (ext === 'webp') mimeType = 'image/webp';
                else if (ext === 'gif') mimeType = 'image/gif';
                else if (ext === 'svg') mimeType = 'image/svg+xml';
                else if (ext === 'mp4') mimeType = 'video/mp4';
                else if (ext === 'webm') mimeType = 'video/webm';
                else if (ext === 'mov') mimeType = 'video/quicktime';

                xhr.setRequestHeader('Authorization', 'Bearer ' + window.SUPABASE_CONFIG.anonKey);
                xhr.setRequestHeader('apikey', window.SUPABASE_CONFIG.anonKey);
                xhr.setRequestHeader('Content-Type', mimeType);
                xhr.setRequestHeader('x-upsert', 'true');

                // Track upload progress
                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        const loadedMB = (event.loaded / (1024 * 1024)).toFixed(1);
                        const totalMB = (event.total / (1024 * 1024)).toFixed(1);
                        updateUploadProgress(percent, loadedMB, totalMB);
                    }
                };

                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        updateUploadProgress(100, fileSizeMB, fileSizeMB, 'Finalizing save...');
                        
                        // Set hidden inputs for PHP backend
                        setHiddenInput(form, 'direct_uploaded_file', uniqueId);
                        setHiddenInput(form, 'direct_file_size_mb', fileSizeMB);
                        setHiddenInput(form, 'direct_upload_target_input', inputName);

                        // Clear file input value so browser does NOT send payload to serverless backend
                        targetInput.value = '';

                        // Mark form as ready and submit
                        form.dataset.directUploadCompleted = 'true';
                        setTimeout(function() {
                            form.submit();
                        }, 300);
                    } else {
                        hideUploadModal();
                        let errorMsg = 'Upload failed with status ' + xhr.status;
                        try {
                            const resJson = JSON.parse(xhr.responseText);
                            if (resJson && resJson.message) errorMsg = resJson.message;
                        } catch (err) {}
                        showUploadError(form, 'Supabase Storage Direct Upload Error: ' + errorMsg);
                    }
                };

                xhr.onerror = function() {
                    hideUploadModal();
                    showUploadError(form, 'Network error during direct upload to Supabase Storage. Please check your internet connection.');
                };

                xhr.send(file);
            });
        });
    });

    function setHiddenInput(form, name, value) {
        let input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function showUploadModal(fileName, fileSizeMB) {
        let modal = document.getElementById('bbDirectUploadModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'bbDirectUploadModal';
            modal.innerHTML = `
                <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.75);backdrop-filter:blur(4px);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;">
                    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,0.3);max-width:480px;width:100%;padding:28px;text-align:center;font-family:'Plus Jakarta Sans',sans-serif;">
                        <div style="width:60px;height:60px;background:#e0f2fe;color:#0284c7;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:16px;">
                            <i class="bi bi-cloud-arrow-up-fill"></i>
                        </div>
                        <h5 style="font-weight:700;margin-bottom:6px;color:#0f172a;">Uploading to Cloud Storage</h5>
                        <p id="bbUploadFileName" style="font-size:13px;color:#64748b;margin-bottom:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                        
                        <div style="height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;margin-bottom:12px;">
                            <div id="bbUploadProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg, #0284c7, #2563eb);transition:width 0.2s ease;border-radius:5px;"></div>
                        </div>
                        
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;color:#475569;font-weight:600;">
                            <span id="bbUploadPercent">0%</span>
                            <span id="bbUploadStatus">0.0 / 0.0 MB</span>
                        </div>
                        <p style="font-size:11.5px;color:#94a3b8;margin-top:14px;margin-bottom:0;">
                            <i class="bi bi-shield-check me-1"></i> Direct Cloud Stream &bull; Fast & Resilient
                        </p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        document.getElementById('bbUploadFileName').textContent = fileName + ' (' + fileSizeMB + ' MB)';
        document.getElementById('bbUploadProgressBar').style.width = '0%';
        document.getElementById('bbUploadPercent').textContent = '0%';
        document.getElementById('bbUploadStatus').textContent = '0.0 / ' + fileSizeMB + ' MB';
        modal.style.display = 'block';
    }

    function updateUploadProgress(percent, loadedMB, totalMB, customStatus) {
        const bar = document.getElementById('bbUploadProgressBar');
        const pctEl = document.getElementById('bbUploadPercent');
        const statEl = document.getElementById('bbUploadStatus');
        if (bar) bar.style.width = percent + '%';
        if (pctEl) pctEl.textContent = percent + '%';
        if (statEl) statEl.textContent = customStatus || (loadedMB + ' / ' + totalMB + ' MB');
    }

    function hideUploadModal() {
        const modal = document.getElementById('bbDirectUploadModal');
        if (modal) modal.style.display = 'none';
    }

    function showUploadError(form, message) {
        let alert = form.querySelector('.direct-upload-alert');
        if (!alert) {
            alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show custom-alert direct-upload-alert mt-3';
            form.prepend(alert);
        }
        alert.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    }
})();
