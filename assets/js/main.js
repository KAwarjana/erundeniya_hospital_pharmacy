/*  assets/js/dynamic-categories.js - Enhanced Version with Unified Image System  */

/* ========= 1.  INITIALIZATION ========= */
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing all dynamic elements...');

    // Load all dynamic data
    loadCategories();
    loadOptionalCategories();
    loadColors();
    loadNumberCandles();

    // Add event listener for category change
    const categorySelect = document.getElementById('category');
    if (categorySelect) {
        categorySelect.addEventListener('change', function (e) {
            console.log('Category changed to:', e.target.value);
            loadSubcategories(e.target.value);
        });
    }

    // Add event listeners for variant checkboxes (will be initialized after colors/numbers load)
    setTimeout(initializeVariantListeners, 500);
    
    // Initialize the unified image system
    initializeUnifiedImageSystem();
});

/* ========= 2.  CATEGORY & SUBCATEGORY LOADING ========= */

/* ---------- Load Categories ---------- */
function loadCategories() {
    console.log('Loading categories...');

    const categorySelect = document.getElementById('category');
    if (!categorySelect) {
        console.error('Category select element not found');
        return;
    }

    categorySelect.innerHTML = '<option value="">Loading categories...</option>';
    categorySelect.disabled = true;

    fetch('load_data.php?action=categories')
        .then(response => {
            console.log('Categories response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Categories data received:', data);

            categorySelect.innerHTML = '<option value="">Select Category</option>';
            categorySelect.disabled = false;

            if (!data.success) {
                throw new Error(data.error || 'Failed to load categories');
            }

            if (!data.categories || !Array.isArray(data.categories)) {
                throw new Error('Invalid categories data format');
            }

            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                categorySelect.appendChild(option);
            });

            console.log(`Loaded ${data.categories.length} categories successfully`);
        })
        .catch(error => {
            console.error('Error loading categories:', error);
            categorySelect.innerHTML = '<option value="">Error loading categories</option>';
            categorySelect.disabled = false;
            showErrorMessage('Failed to load categories. Please refresh the page and try again.');
        });
}

/* ---------- Load Subcategories ---------- */
function loadSubcategories(categoryId) {
    console.log('Loading subcategories for category:', categoryId);

    const subcategorySelect = document.getElementById('subcategory');
    if (!subcategorySelect) {
        console.error('Subcategory select element not found');
        return;
    }

    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
    subcategorySelect.disabled = true;

    if (!categoryId || categoryId === '') {
        subcategorySelect.disabled = false;
        return;
    }

    subcategorySelect.innerHTML = '<option value="">Loading subcategories...</option>';

    fetch(`load_data.php?action=subcategories&category_id=${encodeURIComponent(categoryId)}`)
        .then(response => {
            console.log('Subcategories response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Subcategories data received:', data);

            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            subcategorySelect.disabled = false;

            if (!data.success) {
                throw new Error(data.error || 'Failed to load subcategories');
            }

            if (!data.subcategories || !Array.isArray(data.subcategories)) {
                console.warn('No subcategories found for this category');
                return;
            }

            data.subcategories.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.name;
                subcategorySelect.appendChild(option);
            });

            console.log(`Loaded ${data.subcategories.length} subcategories successfully`);
        })
        .catch(error => {
            console.error('Error loading subcategories:', error);
            subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
            subcategorySelect.disabled = false;
            showErrorMessage('Failed to load subcategories. Please try selecting the category again.');
        });
}

/* ========= 3.  OPTIONAL CATEGORIES LOADING ========= */
function loadOptionalCategories() {
    console.log('Loading optional categories...');

    const optionalCategorySelect = document.getElementById('optionalCategory');
    if (!optionalCategorySelect) {
        console.error('Optional category select element not found');
        return;
    }

    // Store the default "None" option
    const defaultOption = '<option value="">None</option>';
    optionalCategorySelect.innerHTML = defaultOption + '<option value="">Loading...</option>';
    optionalCategorySelect.disabled = true;

    fetch('load_data.php?action=optional_categories')
        .then(response => {
            console.log('Optional categories response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Optional categories data received:', data);

            optionalCategorySelect.innerHTML = defaultOption;
            optionalCategorySelect.disabled = false;

            if (!data.success) {
                throw new Error(data.error || 'Failed to load optional categories');
            }

            if (!data.optional_categories || !Array.isArray(data.optional_categories)) {
                console.warn('No optional categories found');
                return;
            }

            data.optional_categories.forEach(optCat => {
                const option = document.createElement('option');
                option.value = optCat.id;
                option.textContent = optCat.name;
                optionalCategorySelect.appendChild(option);
            });

            console.log(`Loaded ${data.optional_categories.length} optional categories successfully`);
        })
        .catch(error => {
            console.error('Error loading optional categories:', error);
            optionalCategorySelect.innerHTML = defaultOption + '<option value="">Error loading</option>';
            optionalCategorySelect.disabled = false;
            showErrorMessage('Failed to load optional categories. Some features may not work properly.');
        });
}

/* ========= 4.  COLORS LOADING ========= */
function loadColors() {
    console.log('Loading colors...');

    fetch('load_data.php?action=colors')
        .then(response => {
            console.log('Colors response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Colors data received:', data);

            if (!data.success) {
                throw new Error(data.error || 'Failed to load colors');
            }

            if (!data.colors || !Array.isArray(data.colors)) {
                console.warn('No colors found');
                return;
            }

            // Find the colors container
            const colorsContainer = document.getElementById('colorsSection');
            if (!colorsContainer) {
                console.error('Colors container not found');
                return;
            }

            // Create new color checkboxes
            let colorsHTML = '<label class="form-label">Available Colors:</label><div class="mt-2">';

            data.colors.forEach(color => {
                colorsHTML += `
                    <div class="form-check">
                        <input class="form-check-input color-variant-checkbox" type="checkbox" value="${color.id}" id="color${color.id}" data-color-name="${color.name}">
                        <label class="form-check-label" for="color${color.id}">
                            <span class="color-indicator" style="background-color: ${color.hex_code}; width: 12px; height: 12px; display: inline-block; border-radius: 50%; margin-right: 5px; border: 1px solid #ccc;"></span>
                            ${color.name}
                        </label>
                    </div>`;
            });

            colorsHTML += '</div>';
            colorsContainer.innerHTML = colorsHTML;

            console.log(`Loaded ${data.colors.length} colors successfully`);

            // Re-initialize variant listeners after colors are loaded
            setTimeout(() => {
                initializeVariantListeners();
                initializeColorChangeListener(); // Add this for color image upload sections
            }, 100);
        })
        .catch(error => {
            console.error('Error loading colors:', error);
            showErrorMessage('Failed to load colors. Color selection may not work properly.');
        });
}

/* ========= 5.  NUMBERs LOADING ========= */
function loadNumberCandles() {
    console.log('Loading numbers...');

    fetch('load_data.php?action=number_candles')
        .then(response => {
            console.log('Numbers response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Numbers data received:', data);

            if (!data.success) {
                throw new Error(data.error || 'Failed to load numbers');
            }

            if (!data.number_candles || !Array.isArray(data.number_candles)) {
                console.warn('No numbers found');
                return;
            }

            // Find the numbers container
            const numberCandlesContainer = document.getElementById('numberCandlesSection');
            if (!numberCandlesContainer) {
                console.error('Numbers container not found');
                return;
            }

            // Create new number candle checkboxes
            let numbersHTML = '<label class="form-label">Number Candles (for candle products):</label><div class="mt-2"><div class="row">';

            // Split into two columns
            const half = Math.ceil(data.number_candles.length / 2);

            numbersHTML += '<div class="col-6">';
            data.number_candles.slice(0, half).forEach(candle => {
                numbersHTML += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${candle.id}" id="num${candle.id}">
                        <label class="form-check-label" for="num${candle.id}">${candle.number}</label>
                    </div>`;
            });
            numbersHTML += '</div>';

            numbersHTML += '<div class="col-6">';
            data.number_candles.slice(half).forEach(candle => {
                numbersHTML += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${candle.id}" id="num${candle.id}">
                        <label class="form-check-label" for="num${candle.id}">${candle.number}</label>
                    </div>`;
            });
            numbersHTML += '</div>';

            numbersHTML += '</div></div>';
            numberCandlesContainer.innerHTML = numbersHTML;

            console.log(`Loaded ${data.number_candles.length} numbers successfully`);

            // Re-initialize variant listeners after numbers are loaded
            setTimeout(initializeVariantListeners, 100);
        })
        .catch(error => {
            console.error('Error loading numbers:', error);
            showErrorMessage('Failed to load numbers. Number selection may not work properly.');
        });
}

/* ========= 6.  UNIFIED IMAGE SYSTEM ========= */
let globalImageSystem = {
    allImages: [], // All images (main + color specific)
    mainImageIndex: 0,
    maxImages: 10,
    colorImages: {} // colorId: [image objects]
};

function initializeUnifiedImageSystem() {
    console.log('Initializing unified image system...');
    
    const fileInput = document.getElementById('fileUploadInput');
    const defaultImage = document.querySelector('.profile-pic');
    const imageGrid = document.querySelector('.form-group2-grid');
    const imageContainer = document.querySelector('.form-group2-container');

    if (fileInput) {
        fileInput.addEventListener('change', handleMainImageUpload);
        fileInput.setAttribute('accept', '.webp');
        fileInput.setAttribute('multiple', true);
    }

    // Add drag and drop to upload area
    const uploadArea = document.querySelector('.profile-img-edit');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('drop', handleDrop);
        uploadArea.addEventListener('dragleave', handleDragLeave);
    }

    // Initialize display
    updateMainImageDisplay();
}

function handleMainImageUpload(event) {
    const files = Array.from(event.target.files);
    addMainImages(files);
}

function addMainImages(files) {
    // Filter for WebP files only
    const webpFiles = files.filter(file => {
        const isWebp = file.type === 'image/webp' || file.name.toLowerCase().endsWith('.webp');
        if (!isWebp) {
            showMessage(`File "${file.name}" is not a WebP image and will be skipped.`, 'error');
        }
        return isWebp;
    });

    // Check if adding these files would exceed the limit
    if (globalImageSystem.allImages.length + webpFiles.length > globalImageSystem.maxImages) {
        const allowedCount = globalImageSystem.maxImages - globalImageSystem.allImages.length;
        
        Swal.fire({
            icon: 'warning',
            title: 'Upload Limit Exceeded',
            text: `You can only upload ${allowedCount} more image(s). Maximum ${globalImageSystem.maxImages} images allowed.`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6'
        });
        
        webpFiles.splice(allowedCount);
    }

    if (webpFiles.length === 0) return;

    // Process each file
    webpFiles.forEach((file) => {
        if (globalImageSystem.allImages.length < globalImageSystem.maxImages) {
            processImageFile(file, 'main');
        }
    });
}

function processImageFile(file, type = 'main', colorId = null, colorName = null) {
    const reader = new FileReader();

    reader.onload = function (e) {
        const imageData = {
            file: file,
            src: e.target.result,
            name: file.name,
            size: file.size,
            id: Date.now() + Math.random(),
            type: type, // 'main' or 'color'
            colorId: colorId,
            colorName: colorName
        };

        globalImageSystem.allImages.push(imageData);

        // If this is the first image, make it the main image
        if (globalImageSystem.allImages.length === 1) {
            globalImageSystem.mainImageIndex = 0;
        }

        // If it's a color image, also add to color images tracking
        if (type === 'color' && colorId) {
            if (!globalImageSystem.colorImages[colorId]) {
                globalImageSystem.colorImages[colorId] = [];
            }
            globalImageSystem.colorImages[colorId].push(imageData);
        }

        updateMainImageDisplay();
        updateImageCounters();
    };

    reader.readAsDataURL(file);
}

function updateMainImageDisplay() {
    const defaultImage = document.querySelector('.profile-pic');
    const imageGrid = document.querySelector('.form-group2-grid');
    const imageContainer = document.querySelector('.form-group2-container');

    // Update main image
    if (globalImageSystem.allImages.length > 0 && defaultImage) {
        defaultImage.src = globalImageSystem.allImages[globalImageSystem.mainImageIndex].src;
    } else if (defaultImage) {
        defaultImage.src = 'assets/images/error/01.png';
    }

    // Clear existing preview grid
    if (imageGrid) {
        imageGrid.innerHTML = '';
    }

    // Show/hide image container
    if (imageContainer) {
        imageContainer.style.display = globalImageSystem.allImages.length > 0 ? 'block' : 'none';
    }

    // Create preview for each image
    globalImageSystem.allImages.forEach((imageData, index) => {
        createImagePreview(imageData, index);
    });

    // Update the status message
    if (globalImageSystem.allImages.length > 0) {
        const mainImages = globalImageSystem.allImages.filter(img => img.type === 'main').length;
        const colorImages = globalImageSystem.allImages.filter(img => img.type === 'color').length;
        
        showMessage(`Total: ${globalImageSystem.allImages.length} images (${mainImages} main, ${colorImages} color-specific). ${globalImageSystem.maxImages - globalImageSystem.allImages.length} slots remaining.`, 'success');
    }
}

function createImagePreview(imageData, index) {
    const imageGrid = document.querySelector('.form-group2-grid');
    if (!imageGrid) return;

    const previewDiv = document.createElement('div');
    previewDiv.className = 'image-wrapper-div';

    const isMainImage = index === globalImageSystem.mainImageIndex;
    const typeLabel = imageData.type === 'color' ? `Color: ${imageData.colorName}` : 'Main';

    previewDiv.innerHTML = `
        <div class="image-wrapper" style="position: relative;">
            <img src="${imageData.src}" alt="Preview ${index + 1}" class="profile-pic" onclick="setMainImage(${index})" style="cursor: pointer;" />
            <div class="remove-btn" onclick="removeImageFromSystem(${index})">
                <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle opacity="0.4" cx="12" cy="12" r="10" fill="currentColor" />
                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor" />
                </svg>
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); color: white; padding: 2px 4px; font-size: 10px; text-align: center;">
                ${typeLabel}
                ${isMainImage ? ' (Main)' : ''}
            </div>
        </div>
    `;

    imageGrid.appendChild(previewDiv);
}

function setMainImage(index) {
    if (index >= 0 && index < globalImageSystem.allImages.length) {
        globalImageSystem.mainImageIndex = index;
        updateMainImageDisplay();
        showMessage(`Set image ${index + 1} as main image`, 'info');
    }
}

function removeImageFromSystem(index) {
    if (index >= 0 && index < globalImageSystem.allImages.length) {
        const imageData = globalImageSystem.allImages[index];
        
        // Remove from color images tracking if it's a color image
        if (imageData.type === 'color' && imageData.colorId) {
            if (globalImageSystem.colorImages[imageData.colorId]) {
                globalImageSystem.colorImages[imageData.colorId] = globalImageSystem.colorImages[imageData.colorId].filter(img => img.id !== imageData.id);
                if (globalImageSystem.colorImages[imageData.colorId].length === 0) {
                    delete globalImageSystem.colorImages[imageData.colorId];
                }
            }
        }

        // Remove from main array
        globalImageSystem.allImages.splice(index, 1);

        // Adjust main image index if necessary
        if (globalImageSystem.mainImageIndex >= globalImageSystem.allImages.length && globalImageSystem.allImages.length > 0) {
            globalImageSystem.mainImageIndex = globalImageSystem.allImages.length - 1;
        } else if (globalImageSystem.mainImageIndex === index && globalImageSystem.allImages.length > 0) {
            globalImageSystem.mainImageIndex = 0;
        }

        updateMainImageDisplay();
        updateImageCounters();
        
        if (globalImageSystem.allImages.length === 0) {
            showMessage('All images removed.', 'info');
        } else {
            showMessage(`Image removed. ${globalImageSystem.allImages.length} images remaining.`, 'info');
        }
    }
}

function updateImageCounters() {
    // Update any image counter displays
    const imgExtension = document.querySelector('.img-extension');
    if (imgExtension) {
        const remaining = globalImageSystem.maxImages - globalImageSystem.allImages.length;
        imgExtension.innerHTML = `
            <div class="d-inline-block align-items-center">
                <span>Only</span>&nbsp;
                <a href="javascript:void();">.webp</a>&nbsp;
                <span>files allowed (${globalImageSystem.allImages.length}/${globalImageSystem.maxImages} images, ${remaining} slots remaining)</span>
            </div>
        `;
    }
}

/* ========= 7.  COLOR-SPECIFIC IMAGE UPLOAD SECTIONS ========= */
function initializeColorChangeListener() {
    // Add event listeners to color checkboxes to show/hide color image upload sections
    const colorCheckboxes = document.querySelectorAll('.color-variant-checkbox');
    colorCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateColorImageSections);
    });
    
    // Initial update
    updateColorImageSections();
}

function updateColorImageSections() {
    const selectedColors = [...document.querySelectorAll('.color-variant-checkbox:checked')];
    
    // Find or create color image sections container
    let colorImageContainer = document.getElementById('colorImageSections');
    if (!colorImageContainer) {
        // Create it after the main image section
        const mainImageCard = document.querySelector('.col-xl-3.col-lg-4 .card');
        if (mainImageCard && mainImageCard.parentNode) {
            colorImageContainer = document.createElement('div');
            colorImageContainer.id = 'colorImageSections';
            colorImageContainer.className = 'col-12 mt-3';
            mainImageCard.parentNode.insertBefore(colorImageContainer, mainImageCard.nextSibling);
        }
    }
    
    if (!colorImageContainer) return;
    
    if (selectedColors.length === 0) {
        colorImageContainer.innerHTML = '';
        return;
    }
    
    let html = `
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Color-Specific Images</h5>
                <small class="text-muted">Upload images specific to each selected color. These will be added to the main product images.</small>
            </div>
            <div class="card-body">
                <div class="row">
    `;
    
    selectedColors.forEach(checkbox => {
        const colorId = checkbox.value;
        const colorName = checkbox.dataset.colorName || 'Unknown';
        const colorHex = getColorHex(colorId); // You might need to implement this
        
        const existingImages = globalImageSystem.colorImages[colorId] || [];
        
        html += `
            <div class="col-md-6 col-lg-12 mb-4">
                <div class="border rounded p-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="color-indicator" style="background-color: ${colorHex}; width: 16px; height: 16px; display: inline-block; border-radius: 50%; margin-right: 8px; border: 1px solid #ccc;"></span>
                        <h6 class="mb-0">${colorName}</h6>
                    </div>
                    <input type="file" class="form-control color-image-input" 
                           accept=".webp" multiple 
                           data-color-id="${colorId}"
                           data-color-name="${colorName}"
                           onchange="handleColorImageUpload(this)">
                    <small class="text-muted">Upload WebP images for ${colorName} color</small>
                    <div class="mt-2" id="colorImagePreview${colorId}">
                        ${existingImages.length > 0 ? `<small class="text-success">${existingImages.length} images already added</small>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    colorImageContainer.innerHTML = html;
}

function handleColorImageUpload(input) {
    const colorId = input.dataset.colorId;
    const colorName = input.dataset.colorName;
    const files = Array.from(input.files);
    
    if (files.length === 0) return;
    
    // Filter for WebP files
    const webpFiles = files.filter(file => {
        const isWebp = file.type === 'image/webp' || file.name.toLowerCase().endsWith('.webp');
        if (!isWebp) {
            showMessage(`File "${file.name}" is not a WebP image and will be skipped.`, 'error');
        }
        return isWebp;
    });
    
    if (webpFiles.length === 0) return;
    
    // Check total image limit
    if (globalImageSystem.allImages.length + webpFiles.length > globalImageSystem.maxImages) {
        const allowedCount = globalImageSystem.maxImages - globalImageSystem.allImages.length;
        
        Swal.fire({
            icon: 'warning',
            title: 'Upload Limit Exceeded',
            text: `Can only add ${allowedCount} more images. Maximum ${globalImageSystem.maxImages} images allowed.`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6'
        });
        
        webpFiles.splice(allowedCount);
    }
    
    if (webpFiles.length === 0) return;
    
    // Add each image to the global system
    webpFiles.forEach(file => {
        processImageFile(file, 'color', colorId, colorName);
    });
    
    // Update the preview for this color
    const previewDiv = document.getElementById(`colorImagePreview${colorId}`);
    if (previewDiv) {
        const colorImageCount = globalImageSystem.colorImages[colorId] ? globalImageSystem.colorImages[colorId].length : 0;
        previewDiv.innerHTML = `<small class="text-success">${colorImageCount} images added for ${colorName}</small>`;
    }
    
    // Clear the input so the same files can be selected again if needed
    input.value = '';
}

function getColorHex(colorId) {
    // You might need to store color hex codes when loading colors
    // For now, return a default color
    const colorElement = document.querySelector(`#color${colorId} + label .color-indicator`);
    if (colorElement) {
        return colorElement.style.backgroundColor || '#cccccc';
    }
    return '#cccccc';
}

/* ========= 8.  DYNAMIC STOCK QUANTITY INPUTS ========= */
function initializeVariantListeners() {
    console.log('Initializing variant listeners...');

    // Remove existing listeners to avoid duplicates
    const existingCheckboxes = document.querySelectorAll('input[type="checkbox"][id^="color"], input[type="checkbox"][id^="num"]');
    existingCheckboxes.forEach(checkbox => {
        checkbox.removeEventListener('change', updateStockInputs);
    });

    // Add change listeners to all variant checkboxes
    const checkboxes = document.querySelectorAll('input[type="checkbox"][id^="color"], input[type="checkbox"][id^="num"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateStockInputs);
    });

    console.log(`Added listeners to ${checkboxes.length} variant checkboxes`);

    // Initial update
    updateStockInputs();
}

function updateStockInputs() {
    console.log('Updating stock inputs...');

    const stockSection = document.getElementById('stockQuantitySection');
    if (!stockSection) {
        console.error('Stock quantity section not found');
        return;
    }

    /* Helper: collect checked boxes by prefix */
    const collectChecked = (prefix) => {
        return [...document.querySelectorAll(`input[type="checkbox"]:checked`)]
            .filter(cb => cb.id.startsWith(prefix))
            .map(cb => ({
                id: cb.value,
                name: cb.nextElementSibling ? cb.nextElementSibling.textContent.trim() : cb.id,
                element: cb,
                colorName: cb.dataset.colorName || cb.nextElementSibling.textContent.trim()
            }));
    };

    const colors = collectChecked('color');
    const numbers = collectChecked('num');

    console.log('Selected colors:', colors);
    console.log('Selected numbers:', numbers);

    let html = '';

    if (!colors.length && !numbers.length) {
        // No variants selected
        html = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Select colors and/or numbers above to add stock quantities for each variant.
                </div>
            </div>`;
    } else if (colors.length && !numbers.length) {
        // Only colors selected
        html = '<div class="col-12"><h6 class="mb-3">Stock Quantities by Color:</h6></div>';
        colors.forEach(color => {
            html += `
                <div class="form-group col-md-6">
                    <label class="form-label">Stock for ${color.colorName}:</label>
                    <input type="number" class="form-control" name="qty_color_${color.id}" 
                           placeholder="Enter quantity" min="0" required>
                </div>`;
        });
    } else if (!colors.length && numbers.length) {
        // Only numbers selected
        html = '<div class="col-12"><h6 class="mb-3">Stock Quantities by Number:</h6></div>';
        numbers.forEach(number => {
            html += `
                <div class="form-group col-md-6">
                    <label class="form-label">Stock for Number ${number.name}:</label>
                    <input type="number" class="form-control" name="qty_number_${number.id}" 
                           placeholder="Enter quantity" min="0" required>
                </div>`;
        });
    } else {
        // Both colors & numbers => combination matrix
        html = '<div class="col-12"><h6 class="mb-3">Stock Quantities by Color and Number Combinations:</h6></div>';
        colors.forEach(color => {
            numbers.forEach(number => {
                html += `
                    <div class="form-group col-md-4">
                        <label class="form-label">${color.colorName} - Number ${number.name}:</label>
                        <input type="number" class="form-control" name="qty_${color.id}_${number.id}" 
                               placeholder="Enter quantity" min="0" required>
                    </div>`;
            });
        });
    }

    stockSection.innerHTML = html;
    console.log('Stock inputs updated successfully');
}

/* ========= 9.  DRAG AND DROP HANDLERS ========= */
function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.style.backgroundColor = '#e3f2fd';
    event.currentTarget.style.borderColor = '#2196f3';
}

function handleDragLeave(event) {
    event.preventDefault();
    event.currentTarget.style.backgroundColor = '';
    event.currentTarget.style.borderColor = '';
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.style.backgroundColor = '';
    event.currentTarget.style.borderColor = '';

    const files = Array.from(event.dataTransfer.files);
    addMainImages(files);
}

/* ========= 10.  FORM SUBMISSION WITH UNIFIED IMAGE SYSTEM ========= */
function collectFormDataWithUnifiedImages() {
    console.log('Collecting form data with unified images...');
    
    const formData = new FormData();

    // Basic product information
    formData.append('productName', document.getElementById('productName').value.trim());
    formData.append('category', document.getElementById('category').value);
    
    const subcategory = document.getElementById('subcategory').value;
    if (subcategory) {
        formData.append('subcategory', subcategory);
    }

    const shortDescription = document.getElementById('shortDescription').value.trim();
    if (shortDescription) {
        formData.append('shortDescription', shortDescription);
    }

    const longDescription = document.getElementById('longDescription').value.trim();
    if (longDescription) {
        formData.append('longDescription', longDescription);
    }

    formData.append('regularPrice', document.getElementById('regularPrice').value);
    
    const discountPrice = document.getElementById('discountPrice').value;
    if (discountPrice) {
        formData.append('discountPrice', discountPrice);
    }

    const discountPercentage = document.getElementById('discountPercentage').value;
    if (discountPercentage) {
        formData.append('discountPercentage', discountPercentage);
    }

    const optionalCategory = document.getElementById('optionalCategory').value;
    if (optionalCategory) {
        formData.append('optionalCategory', optionalCategory);
    }

    formData.append('status', document.getElementById('status').value);

    // Collect selected colors
    const selectedColors = [];
    document.querySelectorAll('input[type="checkbox"][id^="color"]:checked').forEach(cb => {
        selectedColors.push(cb.value);
    });
    
    selectedColors.forEach(colorId => {
        formData.append('selectedColors[]', colorId);
    });

    // Collect selected numbers
    const selectedNumbers = [];
    document.querySelectorAll('input[type="checkbox"][id^="num"]:checked').forEach(cb => {
        selectedNumbers.push(cb.value);
    });
    
    selectedNumbers.forEach(numberId => {
        formData.append('selectedNumbers[]', numberId);
    });

    // Collect stock quantities
    const stockInputs = document.querySelectorAll('#stockQuantitySection input[type="number"]');
    stockInputs.forEach(input => {
        if (input.name && input.value) {
            formData.append('stockQuantities[' + input.name + ']', input.value);
        }
    });

    // Add ALL images from the unified system as product images
    if (globalImageSystem.allImages && globalImageSystem.allImages.length > 0) {
        globalImageSystem.allImages.forEach((imageData, index) => {
            formData.append('product_images[]', imageData.file);
        });
        formData.append('main_image_index', globalImageSystem.mainImageIndex || 0);
        
        // Create color mapping for images
        const colorImageMapping = {};
        globalImageSystem.allImages.forEach((imageData, index) => {
            if (imageData.type === 'color' && imageData.colorId) {
                if (!colorImageMapping[imageData.colorId]) {
                    colorImageMapping[imageData.colorId] = [];
                }
                colorImageMapping[imageData.colorId].push(index);
            }
        });
        
        // Add color image mapping
        if (Object.keys(colorImageMapping).length > 0) {
            formData.append('color_image_mapping', JSON.stringify(colorImageMapping));
        }
    }

    console.log('Form data collection completed');
    console.log('Total images:', globalImageSystem.allImages.length);
    console.log('Main image index:', globalImageSystem.mainImageIndex);
    
    return formData;
}

/* ========= 11.  FORM VALIDATION ========= */
function validateFormData() {
    const requiredFields = [
        { id: 'productName', name: 'Product Name' },
        { id: 'category', name: 'Category' },
        { id: 'regularPrice', name: 'Regular Price' }
    ];

    let isValid = true;
    const errors = [];

    // Check required fields
    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (!element || !element.value || element.value.trim() === '') {
            isValid = false;
            errors.push(`${field.name} is required`);
            if (element) element.classList.add('is-invalid');
        } else {
            if (element) element.classList.remove('is-invalid');
        }
    });

    // Validate price
    const regularPrice = parseFloat(document.getElementById('regularPrice').value);
    if (regularPrice <= 0) {
        isValid = false;
        errors.push('Regular price must be greater than 0');
    }

    // Validate discount price if provided
    const discountPrice = document.getElementById('discountPrice').value;
    if (discountPrice && parseFloat(discountPrice) >= regularPrice) {
        isValid = false;
        errors.push('Discount price must be less than regular price');
    }

    // Check if at least one variant is selected
    const selectedColors = document.querySelectorAll('input[type="checkbox"][id^="color"]:checked').length;
    const selectedNumbers = document.querySelectorAll('input[type="checkbox"][id^="num"]:checked').length;
    
    if (selectedColors === 0 && selectedNumbers === 0) {
        isValid = false;
        errors.push('Please select at least one color or number variant');
    }

    // Check stock quantities
    const stockInputs = document.querySelectorAll('#stockQuantitySection input[type="number"]');
    let hasValidStock = false;
    stockInputs.forEach(input => {
        if (input.value && parseInt(input.value) > 0) {
            hasValidStock = true;
        }
    });

    if (stockInputs.length > 0 && !hasValidStock) {
        isValid = false;
        errors.push('Please enter at least one stock quantity greater than 0');
    }

    // Image validation
    if (globalImageSystem.allImages.length === 0) {
        // Warning but not blocking
        errors.push('Warning: No images uploaded. Products without images may not display properly.');
    }

    // Validate image files
    globalImageSystem.allImages.forEach(imageData => {
        const file = imageData.file;
        const isWebp = file.type === 'image/webp' || file.name.toLowerCase().endsWith('.webp');
        if (!isWebp) {
            isValid = false;
            errors.push(`Image "${file.name}" must be a WebP file`);
        }
        
        if (file.size > 5 * 1024 * 1024) {
            isValid = false;
            errors.push(`Image "${file.name}" is too large (max 5MB)`);
        }
    });

    if (!isValid || errors.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: 'Please fix the following issues:<br>• ' + errors.join('<br>• '),
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33'
        });
    }

    return isValid && errors.filter(e => !e.startsWith('Warning:')).length === 0;
}

function submitProductData(formData) {
    // Show loading state with SweetAlert
    Swal.fire({
        title: 'Adding Product...',
        html: 'Please wait while we process your product.',
        allowEscapeKey: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('process_add_product.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: `Product "${data.data.name}" added successfully with ${data.data.images_count} images!`,
                    confirmButtonText: 'Add Another Product',
                    showCancelButton: true,
                    cancelButtonText: 'Manage Products',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#007bff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        location.reload();
                    } else if (result.isDismissed) {
                        window.location.href = 'manageProduct.php';
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'Failed to add product',
                    confirmButtonText: 'Try Again',
                    confirmButtonColor: '#d33'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'An error occurred while adding the product. Please check your connection and try again.',
                confirmButtonText: 'Try Again',
                confirmButtonColor: '#d33'
            });
        });
}

/* ========= 12.  PRICE CALCULATION HELPERS ========= */
function calculateDiscountPercentage() {
    const regularPrice = parseFloat(document.getElementById('regularPrice').value) || 0;
    const discountPrice = parseFloat(document.getElementById('discountPrice').value) || 0;
    const discountPercentageField = document.getElementById('discountPercentage');

    if (regularPrice > 0 && discountPrice > 0 && discountPrice < regularPrice) {
        const percentage = Math.round(((regularPrice - discountPrice) / regularPrice) * 100);
        discountPercentageField.value = percentage;
    } else {
        discountPercentageField.value = '';
    }
}

function calculateDiscountPrice() {
    const regularPrice = parseFloat(document.getElementById('regularPrice').value) || 0;
    const discountPercentage = parseFloat(document.getElementById('discountPercentage').value) || 0;
    const discountPriceField = document.getElementById('discountPrice');

    if (regularPrice > 0 && discountPercentage > 0 && discountPercentage <= 100) {
        const discountAmount = (regularPrice * discountPercentage) / 100;
        const finalPrice = regularPrice - discountAmount;
        discountPriceField.value = finalPrice.toFixed(2);
    }
}

// Add event listeners for automatic calculation
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const regularPriceField = document.getElementById('regularPrice');
        const discountPriceField = document.getElementById('discountPrice');
        const discountPercentageField = document.getElementById('discountPercentage');

        if (regularPriceField && discountPriceField) {
            discountPriceField.addEventListener('input', calculateDiscountPercentage);
            regularPriceField.addEventListener('input', calculateDiscountPercentage);
        }

        if (regularPriceField && discountPercentageField) {
            discountPercentageField.addEventListener('input', calculateDiscountPrice);
            regularPriceField.addEventListener('input', () => {
                if (discountPercentageField.value) {
                    calculateDiscountPrice();
                }
            });
        }
    }, 1000);
});

/* ========= 13.  FORM SUBMISSION HANDLER ========= */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addProductForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (validateFormData()) {
                console.log('Form is valid, collecting data...');

                const formData = collectFormDataWithUnifiedImages();
                console.log('Form data with unified images collected');
                submitProductData(formData);
            }
        });
    }
});

/* ========= 14.  UTILITY FUNCTIONS ========= */
function showErrorMessage(message) {
    let errorAlert = document.getElementById('dynamicErrorAlert');
    if (!errorAlert) {
        errorAlert = document.createElement('div');
        errorAlert.id = 'dynamicErrorAlert';
        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
        errorAlert.style.marginTop = '1rem';

        const cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(errorAlert, cardBody.firstChild);
        }
    }

    errorAlert.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function showSuccessMessage(message) {
    const successAlert = document.createElement('div');
    successAlert.className = 'alert alert-success alert-dismissible fade show';
    successAlert.style.marginTop = '1rem';
    successAlert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    const cardBody = document.querySelector('.card-body');
    if (cardBody) {
        cardBody.insertBefore(successAlert, cardBody.firstChild);
        successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.upload-message');
    existingMessages.forEach(msg => msg.remove());

    const messageDiv = document.createElement('div');
    messageDiv.className = `upload-message alert ${type === 'error' ? 'alert-danger' : type === 'info' ? 'alert-info' : 'alert-success'}`;
    messageDiv.style.marginTop = '10px';
    messageDiv.style.padding = '8px 12px';
    messageDiv.style.borderRadius = '4px';
    messageDiv.style.fontSize = '0.875rem';
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'info' ? 'info-circle' : 'check-circle'} me-2"></i>
        ${message}
    `;

    const uploadContainer = document.querySelector('.form-group-div');
    if (uploadContainer) {
        uploadContainer.appendChild(messageDiv);

        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 3000);
        }
    }
}

/* ========= 15.  GLOBAL FUNCTIONS ========= */
// Make functions globally available
window.setMainImage = setMainImage;
window.removeImageFromSystem = removeImageFromSystem;
window.handleColorImageUpload = handleColorImageUpload;
window.collectFormDataWithUnifiedImages = collectFormDataWithUnifiedImages;
window.submitProductData = submitProductData;
window.validateFormData = validateFormData;
window.globalImageSystem = globalImageSystem;

// Debug functions
window.debugImageSystem = function() {
    console.log('=== IMAGE SYSTEM DEBUG ===');
    console.log('Total images:', globalImageSystem.allImages.length);
    console.log('Main image index:', globalImageSystem.mainImageIndex);
    console.log('Color images breakdown:');
    Object.keys(globalImageSystem.colorImages).forEach(colorId => {
        console.log(`  Color ${colorId}:`, globalImageSystem.colorImages[colorId].length, 'images');
    });
    console.log('All images:', globalImageSystem.allImages.map(img => ({
        name: img.name,
        type: img.type,
        colorId: img.colorId,
        colorName: img.colorName
    })));
    console.log('========================');
};

console.log('Enhanced unified image system initialized successfully');


// Add-on JavaScript for Read More functionality
function addReadMoreFunctionality() {
    // Find all product cards and add read more functionality
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        // Skip if already processed
        if (card.querySelector('.read-more-toggle')) {
            return;
        }
        
        const productId = getProductIdFromCard(card);
        const listGroup = card.querySelector('.list-group');
        
        if (!listGroup) return;
        
        // Create expanded content container
        const expandedContent = document.createElement('div');
        expandedContent.className = 'expanded-content';
        
        // Move detailed items to expanded content
        const detailedItems = [];
        const listItems = listGroup.querySelectorAll('.list-group-item');
        
        listItems.forEach((item, index) => {
            const itemText = item.textContent.toLowerCase();
            // Keep basic items (Category, Price, Status), move others to expanded
            if (itemText.includes('colors:') || itemText.includes('numbers:') || index > 2) {
                detailedItems.push(item.cloneNode(true));
                item.style.display = 'none'; // Hide original
            }
        });
        
        // Add detailed items to expanded content
        if (detailedItems.length > 0) {
            detailedItems.forEach(item => {
                const section = document.createElement('div');
                section.className = 'expanded-section';
                section.appendChild(item);
                expandedContent.appendChild(section);
            });
            
            // Create read more button
            const readMoreBtn = document.createElement('button');
            readMoreBtn.className = 'read-more-toggle';
            readMoreBtn.type = 'button';
            readMoreBtn.innerHTML = `
                <span class="read-more-text">Read More</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                    <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
            
            // Add click event
            readMoreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleCardExpansion(card);
            });
            
            // Insert read more button before the "View Product" button
            const viewBtn = card.querySelector('a[href*="updateProduct.php"]');
            if (viewBtn) {
                viewBtn.parentNode.insertBefore(readMoreBtn, viewBtn);
                viewBtn.parentNode.insertBefore(expandedContent, viewBtn);
            }
        }
    });
}

function toggleCardExpansion(card) {
    const isExpanded = card.classList.contains('card-expanded');
    const readMoreText = card.querySelector('.read-more-text');
    
    if (isExpanded) {
        card.classList.remove('card-expanded');
        readMoreText.textContent = 'Read More';
    } else {
        card.classList.add('card-expanded');
        readMoreText.textContent = 'Read Less';
    }
}

function getProductIdFromCard(card) {
    const viewBtn = card.querySelector('a[href*="updateProduct.php"]');
    if (viewBtn) {
        const url = viewBtn.getAttribute('href');
        const match = url.match(/id=(\d+)/);
        return match ? match[1] : null;
    }
    return null;
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for existing scripts to load
    setTimeout(() => {
        addReadMoreFunctionality();
    }, 100);
});

// Also run when new products are loaded via AJAX/search
document.addEventListener('productsUpdated', function() {
    addReadMoreFunctionality();
});