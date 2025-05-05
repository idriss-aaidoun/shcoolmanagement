/**
 * ENSA Projects Management System
 * Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('invalid');
                    
                    // Create error message if it doesn't exist
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('error-message');
                        errorMsg.style.color = 'red';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.marginTop = '5px';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                    
                    errorMsg.textContent = 'Ce champ est requis';
                } else {
                    field.classList.remove('invalid');
                    
                    // Remove error message if it exists
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    });
    
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileList = input.files;
            const previewContainer = document.getElementById(`${input.id}-preview`);
            
            if (previewContainer) {
                previewContainer.innerHTML = '';
                
                if (fileList.length > 0) {
                    const fileInfo = document.createElement('div');
                    fileInfo.classList.add('file-info');
                    
                    for (let i = 0; i < fileList.length; i++) {
                        const file = fileList[i];
                        const fileItem = document.createElement('div');
                        fileItem.classList.add('file-item');
                        
                        // File icon based on extension
                        let fileIcon = 'fa-file';
                        const extension = file.name.split('.').pop().toLowerCase();
                        
                        if (['pdf'].includes(extension)) {
                            fileIcon = 'fa-file-pdf';
                        } else if (['doc', 'docx'].includes(extension)) {
                            fileIcon = 'fa-file-word';
                        } else if (['ppt', 'pptx'].includes(extension)) {
                            fileIcon = 'fa-file-powerpoint';
                        } else if (['zip', 'rar'].includes(extension)) {
                            fileIcon = 'fa-file-archive';
                        } else if (['txt'].includes(extension)) {
                            fileIcon = 'fa-file-alt';
                        }
                        
                        fileItem.innerHTML = `
                            <i class="fas ${fileIcon}"></i>
                            <span>${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>
                        `;
                        
                        fileInfo.appendChild(fileItem);
                    }
                    
                    previewContainer.appendChild(fileInfo);
                }
            }
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément? Cette action est irréversible.')) {
                event.preventDefault();
            }
        });
    });
    
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Toggle password visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
});
