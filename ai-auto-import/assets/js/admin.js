jQuery(document).ready(function($) {
    const form = $('#ai-auto-import-form');
    const uploadArea = $('#upload-area');
    const previewArea = $('#preview-area');
    const photoPreview = $('#photo-preview');
    const resultsArea = $('#results-area');
    const resultsContent = $('.results-content');
    
    // Drag and drop handling
    uploadArea.on('dragenter dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    uploadArea.on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    uploadArea.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        $('#car-photo')[0].files = files;
        handleFileSelect(files[0]);
    });

    // File input change
    $('#car-photo').on('change', function(e) {
        handleFileSelect(this.files[0]);
    });

    // Remove photo button
    $('#remove-photo').on('click', function() {
        $('#car-photo').val('');
        previewArea.hide();
        uploadArea.show();
    });

    // Form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ai_auto_import_process');
        formData.append('nonce', aiAutoImport.nonce);

        resultsArea.show();
        resultsContent.empty();
        $('.loading').show();

        $.ajax({
            url: aiAutoImport.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('.loading').hide();
                
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showError(response.data.message);
                }
            },
            error: function() {
                $('.loading').hide();
                showError('Er is een fout opgetreden bij het verwerken van de foto.');
            }
        });
    });

    // Retry import button
    $('.retry-import').on('click', function() {
        const id = $(this).data('id');
        const button = $(this);
        
        button.prop('disabled', true).text('Bezig...');

        $.post(aiAutoImport.ajaxUrl, {
            action: 'ai_auto_import_retry',
            nonce: aiAutoImport.nonce,
            id: id
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                button.prop('disabled', false).text('Opnieuw Proberen');
            }
        })
        .fail(function() {
            alert('Er is een fout opgetreden.');
            button.prop('disabled', false).text('Opnieuw Proberen');
        });
    });

    function handleFileSelect(file) {
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.attr('src', e.target.result);
                uploadArea.hide();
                previewArea.show();
            };
            reader.readAsDataURL(file);
        }
    }

    function showSuccess(data) {
        const html = `
            <div class="notice notice-success">
                <p>Auto succesvol verwerkt!</p>
            </div>
            <div class="vehicle-details">
                <h3>Gevonden gegevens:</h3>
                <ul>
                    <li><strong>Kenteken:</strong> ${data.license_plate}</li>
                    <li><strong>Merk:</strong> ${data.brand}</li>
                    <li><strong>Model:</strong> ${data.model}</li>
                    <li><strong>Jaar:</strong> ${data.year}</li>
                </ul>
                <p><a href="${data.edit_url}" class="button button-primary">Bewerk Occasion</a></p>
            </div>
        `;
        resultsContent.html(html);
    }

    function showError(message) {
        const html = `
            <div class="notice notice-error">
                <p>${message}</p>
            </div>
        `;
        resultsContent.html(html);
    }
});
