"use strict";
import './framework.less';
import * as turbo from './turbo';
import { config as turboConfig } from './turbo/config';
import { initDatePickers } from './date-picker';

// For plugins that add date fields after load ( a repeater row ): ddfwInitDatePickers( row ).
window.ddfwInitDatePickers = initDatePickers;

const ddfw = jQuery.noConflict();

// Must run while framework.js is evaluated: plugin scripts depend on it and execute right after.
turbo.prepare();

// ===== MOBILE SIDEBAR TOGGLE =====

// Function to close header drawer
const closeMobileHeader = () => {
    const drawer = document.querySelector('.ddfw-mobile-header-drawer');
    const overlay = document.querySelector('.ddfw-mobile-header-overlay');
    if (drawer) drawer.classList.remove('ddfw-mobile-header-open');
    if (overlay) overlay.classList.remove('ddfw-mobile-header-overlay-active');
}

// Function to open header drawer
const openMobileHeader = () => {
    const drawer = document.querySelector('.ddfw-mobile-header-drawer');
    const overlay = document.querySelector('.ddfw-mobile-header-overlay');
    if (drawer) drawer.classList.add('ddfw-mobile-header-open');
    if (overlay) overlay.classList.add('ddfw-mobile-header-overlay-active');
}

// Function to close settings drawer
const closeMobileSettings = () => {
    const wrapper = document.querySelector('.ddfw-left-tab-wrapper');
    const overlay = document.querySelector('.ddfw-mobile-settings-overlay');
    const toggle = document.querySelector('.ddfw-mobile-settings-toggle');
    if (wrapper) wrapper.classList.remove('ddfw-mobile-settings-open');
    if (overlay) overlay.classList.remove('ddfw-mobile-settings-overlay-active');
    if (toggle) toggle.classList.remove('ddfw-hide');
}

// Function to open settings drawer
const openMobileSettings = () => {
    const wrapper = document.querySelector('.ddfw-left-tab-wrapper');
    const overlay = document.querySelector('.ddfw-mobile-settings-overlay');
    const toggle = document.querySelector('.ddfw-mobile-settings-toggle');
    if (wrapper) wrapper.classList.add('ddfw-mobile-settings-open');
    if (overlay) overlay.classList.add('ddfw-mobile-settings-overlay-active');
    if (toggle) toggle.classList.add('ddfw-hide');
}

/**
 * Open the media modal for an image field.
 *
 * @param {HTMLElement} _self Upload button.
 */
const ddfwOpenImageUploader = _self => {
    const id = _self.getAttribute('data-id');
    const custom_uploader = wp.media({
        title: ddfwFrameworkObject.i18n.selectImage,
        button: {
            text: ddfwFrameworkObject.i18n.useImage,
        },
        library: {
            type: ['image']
        },
        multiple: false  // Set this to true to allow multiple files to be selected
    }).on('select', () => {
        const attachment = custom_uploader.state().get('selection').first().toJSON();
        const closestTr = _self.closest('.ddfw-image-upload') || _self.closest('tr');
        closestTr.querySelector('#' + id).value = attachment.id;
        closestTr.querySelector('.ddfw-image-preview-container').classList.remove('ddfw-hide');
        closestTr.querySelector('.ddfw-image-preview-wrapper img').setAttribute('src', attachment.url);
        closestTr.querySelector('.ddfw-image-upload-wrapper').classList.add('ddfw-hide');
        closestTr.querySelector('.ddfw-remove-image-button').classList.remove('ddfw-hide');
        closestTr.querySelector('.ddfw-upload-image-button[data-id="' + id + '"]').classList.remove('ddfw-hide');

        // Trigger custom event for image upload
        ddfw(document).trigger('ddfw_image_uploaded', { id: id, url: attachment.url, attachment: attachment });
    }).open();
};

const ddfwToggleFields = element => {
    const showFields = JSON.parse(element.getAttribute('data-show-fields'));
    const onlyHideFieldsValue = element.getAttribute('data-only-hide-fields');
    const onlyHideFields = onlyHideFieldsValue ? JSON.parse(onlyHideFieldsValue) : false;
    const showedFields = [];

    // Determine the selected value
    let selectedValue = '';
    if (element.type === 'radio') {
        const checkedRadio = document.querySelector(`input[name="${element.name}"]:checked`);
        if (checkedRadio) {
            selectedValue = checkedRadio.value;
        }
    } else {
        selectedValue = element.value;
    }

    Object.keys(showFields).forEach(key => {
        const relatedFields = showFields[key];

        relatedFields.forEach(relatedField => {
            const field = document.getElementById(relatedField);

            if (field) {
                if (selectedValue === key) {
                    field.closest('tr').classList.remove('ddfw-hide');

                    showedFields.push(field);

                    if (onlyHideFields && onlyHideFields[key] && onlyHideFields[key].includes(relatedField)) {
                        field.closest('tr').classList.add('ddfw-hide');
                        showedFields.pop(field);
                    }
                } else if (!showedFields.includes(field)) {
                    field.closest('tr').classList.add('ddfw-hide');
                }
            }
        });
    });
}

const SHOW_FIELDS_SELECTOR = '[data-show-fields]:not([data-show-fields="false"])';

// ===== SELECT2 INITIALIZATION =====

/**
 * Initialize Select2 for all select elements within a container
 * @param {HTMLElement} container - The container element (defaults to document)
 */
window.ddfwInitializeSelect2 = function(container = document) {
    const selectElements = container.querySelectorAll('select');
    if (selectElements.length) {
        selectElements.forEach(select => {
            // Skip if already initialized, or owned by React (@wordpress/components SelectControl): select2
            // fires jQuery events React never sees, and whether React rendered first is a race.
            if (ddfw(select).hasClass('select2-hidden-accessible') || ddfw(select).hasClass('ddfw-products') || ddfw(select).hasClass('ddfw-categories') || ddfw(select).hasClass('ddfw-users') || select.classList.contains('components-select-control__input') || select.classList.contains('ddfw-dash-filter-select')) {
                return;
            }

            ddfw(select).select2({
                minimumResultsForSearch: -1,
                allowClear: ddfw(select).data('allow_clear') === true,
                placeholder: {
                    id: '',
                    text: ddfw(select).data('placeholder') || 'Choose an option'
                }
            });
        });
    }
};

/**
 * Initialize Select2 for product select elements within a container
 * @param {HTMLElement} container - The container element (defaults to document)
 */
window.ddfwInitializeProductsSelect2 = function(container = document) {
    const productSelects = container.querySelectorAll('.ddfw-products');
    if (productSelects.length) {
        productSelects.forEach(select => {
            if (ddfw(select).hasClass('select2-hidden-accessible')) {
                return;
            }

            ddfw(select).select2({
                ajax: {
                    method: 'post',
                    url: ddfwFrameworkObject.ajax.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => {
                        return {
                            query: params.term,
                            action: 'ddfw_get_products_list',
                            nonce: ddfwFrameworkObject.ajax.ajaxNonce,
                        };
                    },
                    processResults: products => {
                        let options = [];
                        if (products != undefined && products != null) {
                            products.forEach(product => {
                                options.push({
                                    id: product.ID,
                                    text: product.title
                                });
                            });
                        }
                        return { results: options };
                    },
                    cache: true
                },
                multiple: select.hasAttribute('multiple'),
                minimumInputLength: 1,
                language: {
                    inputTooShort: args => `${ddfwFrameworkObject.i18n.pleaseEnter} ${args.minimum - args.input.length} ${ddfwFrameworkObject.i18n.moreCharacter}`,
                    noResults: () => ddfwFrameworkObject.i18n.noResult,
                }
            });
        });
    }
};

/**
 * Initialize Select2 for category select elements within a container
 * @param {HTMLElement} container - The container element (defaults to document)
 */
window.ddfwInitializeCategoriesSelect2 = function(container = document) {
    const categorySelects = container.querySelectorAll('.ddfw-categories');
    if (categorySelects.length) {
        categorySelects.forEach(select => {
            // Skip if already initialized
            if (ddfw(select).hasClass('select2-hidden-accessible')) {
                return;
            }

            ddfw(select).select2({
                ajax: {
                    method: 'post',
                    url: ddfwFrameworkObject.ajax.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => {
                        return {
                            query: params.term,
                            action: 'ddfw_get_categories_list',
                            nonce: ddfwFrameworkObject.ajax.ajaxNonce,
                        };
                    },
                    processResults: response => {
                        let options = [];
                        let cats = response.categories;

                        if (cats != undefined && cats != null) {
                            cats.forEach(category => {
                                options.push({
                                    id: category.term_id,
                                    text: category.name
                                });
                            });
                        }

                        return { results: options };
                    },
                    cache: true
                },
                multiple: select.hasAttribute('multiple'),
                minimumInputLength: 1,
                language: {
                    inputTooShort: args => `${ddfwFrameworkObject.i18n.pleaseEnter} ${args.minimum - args.input.length} ${ddfwFrameworkObject.i18n.moreCharacter}`,
                    noResults: () => ddfwFrameworkObject.i18n.noResult,
                }
            });
        });
    }
};

/**
 * Initialize Select2 for user select elements within a container
 * @param {HTMLElement} container - The container element (defaults to document)
 */
window.ddfwInitializeUsersSelect2 = function(container = document) {
    const userSelects = container.querySelectorAll('.ddfw-users');
    if (userSelects.length) {
        userSelects.forEach(select => {
            if (ddfw(select).hasClass('select2-hidden-accessible')) {
                return;
            }

            ddfw(select).select2({
                ajax: {
                    method: 'post',
                    url: ddfwFrameworkObject.ajax.ajaxUrl,   // AJAX URL is predefined in WordPress admin
                    dataType: 'json',
                    delay: 250,                           // delay in ms while typing when to perform a AJAX search
                    data: params => {
                        return {
                            query: params.term,                          // search query
                            action: 'ddfw_get_users_list',
                            nonce: ddfwFrameworkObject.ajax.ajaxNonce,
                            role: select.getAttribute('data-role'),
                        };
                    },
                    processResults: response => {
                        let options = [];
                        const users = response.users;
                        if (users != undefined && users != null && users.length) {
                            // users is the array of objects, and each of them contains value and the Label of the option
                            users.forEach(user => {
                                options.push({
                                    id: user.ID,
                                    text: `(#${user.ID}) ${user.user_login} <${user.user_email}>`
                                });
                            });
                        }
                        return {
                            results: options
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1,// the minimum of symbols to input before perform a search
                language: {
                    inputTooShort: args => {
                        const remain = args.minimum - args.input.length;
                        return `${ddfwFrameworkObject.i18n.pleaseEnter} ${remain} ${ddfwFrameworkObject.i18n.moreCharacter}`;
                    },
                    noResults: () => ddfwFrameworkObject.i18n.noResult,
                }
            });
        });
    }
};

/**
 * Initialize every framework field inside a container. Safe to call repeatedly: already
 * initialized elements are skipped. Runs for the full page and for every soft-loaded view.
 *
 * @param {HTMLElement|Document} container Container.
 */
/**
 * Turn short success notices in the plugin's content area into a toast at the top centre.
 * Errors, warnings and notices with links or controls stay inline: a message that fades
 * must never hide something the admin has to read twice or act on.
 *
 * @param {Element|Document} container View being initialised.
 */
const ddfwToastSuccessNotices = (container = document) => {
    const notices = [...container.querySelectorAll('.ddfw-template-wrapper .notice-success, .ddfw-template-wrapper .updated')]
        .filter(notice => !notice.querySelector('a, button:not(.notice-dismiss), input, select, form') && notice.textContent.trim().length <= 120);

    if (!notices.length) {
        return;
    }

    let region = document.querySelector('.ddfw-toast-region');
    if (!region) {
        region = document.createElement('div');
        region.className = 'ddfw-toast-region';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);
    }

    notices.forEach(notice => {
        const toast = document.createElement('div');
        const text = document.createElement('span');
        const close = document.createElement('button');
        let timer;

        text.textContent = notice.textContent.trim();
        close.type = 'button';
        close.className = 'ddfw-toast-close';
        close.setAttribute('aria-label', ddfwFrameworkObject.i18n.dismiss || 'Dismiss');
        close.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
        toast.className = 'ddfw-toast';
        toast.innerHTML = '<span class="ddfw-toast-icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>';
        toast.append(text, close);

        const hide = () => {
            toast.classList.add('ddfw-toast-leaving');
            setTimeout(() => toast.remove(), 200);
        };
        const start = () => {
            timer = setTimeout(hide, 4500);
        };

        // Hovering holds the toast so it can be read; leaving restarts the clock.
        toast.addEventListener('mouseenter', () => clearTimeout(timer));
        toast.addEventListener('mouseleave', start);
        close.addEventListener('click', hide);

        notice.remove();
        region.appendChild(toast);
        start();
    });
};

// Edit screens: the title bar (title, back, Save) sticks under the plugin header so Save stays in
// reach on long forms. A sentinel just above it tells us when it is stuck, and the bar tightens up.
const ddfwWatchEditScreenBar = (container) => {
    const bar = container.querySelector('.ddfw-edit-screen-bar');

    if (!bar || bar.dataset.ddfwWatched || !('IntersectionObserver' in window)) {
        return;
    }

    bar.dataset.ddfwWatched = '1';

    const sentinel = document.createElement('div');
    sentinel.className = 'ddfw-edit-screen-bar-sentinel';
    sentinel.setAttribute('aria-hidden', 'true');
    bar.before(sentinel);

    // The bar sticks at the bottom of the plugin header; read it, since the admin bar height varies.
    const top = parseFloat(getComputedStyle(bar).top) || 0;

    new IntersectionObserver(([entry]) => {
        bar.classList.toggle('is-stuck', !entry.isIntersecting && entry.boundingClientRect.top < top);
    }, { rootMargin: `-${top}px 0px 0px 0px` }).observe(sentinel);
};

const ddfwInitView = (container = document) => {
    ddfwToastSuccessNotices(container);
    ddfwWatchEditScreenBar(container);
    window.ddfwInitializeSelect2(container);
    window.ddfwInitializeProductsSelect2(container);
    window.ddfwInitializeCategoriesSelect2(container);
    window.ddfwInitializeUsersSelect2(container);
    initDatePickers(container);

    // Change handling is delegated (see below); apply the initial state here.
    container.querySelectorAll(SHOW_FIELDS_SELECTOR).forEach(ddfwToggleFields);

    // Initialize the color picker
    container.querySelectorAll('.ddfw-color-picker').forEach(element => {
        if (element.closest('.wp-picker-container')) {
            return;
        }

        ddfw(element).wpColorPicker({
            width: 220,
            mode: 'hsl',
            // Shades a store actually uses ( brand blue, text, greys, white, status colours ), in
            // place of Iris's rainbow row.
            palettes: ['#0256ff', '#111827', '#6b7280', '#e5e7eb', '#ffffff', '#16a34a', '#dc2626', '#f59e0b'],
            change: (event, ui) => {
                // Trigger custom event for color change
                ddfw(document).trigger('ddfw_color_picker_change', {
                    id: element.id,
                    color: ui.color.toString()
                });
            }
        });

        // wpColorPicker builds its own swatch toggle and clear button next to the input,
        // and those stay clickable even when the input itself is disabled.
        if (element.disabled) {
            element.closest('.wp-picker-container')?.classList.add('ddfw-color-picker-disabled');
        }
    });

    // List table search boxes ship without a placeholder; reuse the translated button label.
    container.querySelectorAll('.search-box').forEach(box => {
        const input = box.querySelector('input[type="search"]:not([placeholder])');
        const submit = box.querySelector('[type="submit"]');
        if (input && submit) {
            input.placeholder = `${submit.value}…`;
        }
    });

    container.querySelectorAll('.ddfw-upgrade-to-pro-tag-wrapper').forEach(button => {
        // Add pro tag if not already added
        if (!button.querySelector('.ddfw-pro-tag')) {
            const proTag = document.createElement('span');
            proTag.className = 'ddfw-pro-tag';
            proTag.innerHTML = '<span class="dashicons dashicons-lock"></span> Pro';
            button.appendChild(proTag);
        }
    });
};

window.ddfw = Object.assign(window.ddfw || {}, {
    initView: ddfwInitView,
    visit: turbo.visit,
});

document.addEventListener('DOMContentLoaded', () => {
    // Consolidated click event handler for better performance
    document.addEventListener('click', e => {
        const uploadImageButton = e.target.closest && e.target.closest('.ddfw-upload-image-button');

        // Image upload button
        if (uploadImageButton) {
            e.preventDefault();
            ddfwOpenImageUploader(uploadImageButton);
        }
        // Delete confirmation for individual items
        else if (e.target.matches('.devdiggers-wrap .row-actions span.delete a[href*="action=delete"]')) {
            if (!confirm(ddfwFrameworkObject.i18n.deleteConfirm)) {
                e.preventDefault(); // Prevent the action if user cancels
            }
        }
        // Delete confirmation for bulk actions
        else if (e.target.matches('.devdiggers-wrap #doaction') || e.target.matches('.devdiggers-wrap #doaction2')) {
            const bulkAction = document.querySelector('select[name="action"]').value;
            const bulkAction2 = document.querySelector('select[name="action2"]').value;

            if (('delete' === bulkAction || 'delete' === bulkAction2) && !confirm(ddfwFrameworkObject.i18n.deleteConfirm)) {
                e.preventDefault(); // Prevent the bulk delete action if user cancels
            }
        }
        // Image remove button
        // ( closest(): a click on an icon inside the button used to miss it. )
        else if (e.target.closest && e.target.closest('.ddfw-remove-image-button')) {
            e.preventDefault();
            const _self = e.target.closest('.ddfw-remove-image-button');
            const field = _self.closest('.ddfw-image-upload') || _self.closest('tr');
            const id = _self.getAttribute('data-id');
            field.querySelector('#' + id).value = '';

            const defaultImageURL = field.querySelector('#' + id + '-default-image').value;

            if (defaultImageURL) {
                field.querySelector('.ddfw-image-preview-wrapper img').setAttribute('src', defaultImageURL);
            } else {
                field.querySelector('.ddfw-image-preview-container').classList.add('ddfw-hide');
                field.querySelector('.ddfw-image-upload-wrapper').classList.remove('ddfw-hide');
            }

            _self.classList.add('ddfw-hide');

            // Trigger custom event for image removal
            ddfw(document).trigger('ddfw_image_removed', { id: id });
        }
        // Mobile Header Toggle (Hamburger)
        else if (e.target.matches('.ddfw-mobile-header-toggle') || e.target.closest('.ddfw-mobile-header-toggle')) {
            e.preventDefault();
            const drawer = document.querySelector('.ddfw-mobile-header-drawer');

            if (drawer && drawer.classList.contains('ddfw-mobile-header-open')) {
                closeMobileHeader();
            } else {
                openMobileHeader();
                closeMobileSettings(); // Close settings if open
            }
        }
        // Close header when clicking its close button
        else if (e.target.matches('.ddfw-mobile-header-close') || e.target.closest('.ddfw-mobile-header-close')) {
            e.preventDefault();
            closeMobileHeader();
        }
        // Close header when clicking its overlay
        else if (e.target.matches('.ddfw-mobile-header-overlay')) {
            closeMobileHeader();
        }
        // Close header when clicking on a tab link inside it
        else if (e.target.matches('.ddfw-mobile-header-menu .ddfw-left-tab')) {
            closeMobileHeader();
        }

        // Mobile Settings Toggle (FAB)
        else if (e.target.matches('.ddfw-mobile-settings-toggle') || e.target.closest('.ddfw-mobile-settings-toggle')) {
            e.preventDefault();
            const wrapper = document.querySelector('.ddfw-left-tab-wrapper');

            if (wrapper && wrapper.classList.contains('ddfw-mobile-settings-open')) {
                closeMobileSettings();
            } else {
                openMobileSettings();
                closeMobileHeader(); // Close header if open
            }
        }
        // Close settings when clicking its close button
        else if (e.target.matches('.ddfw-mobile-settings-close') || e.target.closest('.ddfw-mobile-settings-close')) {
            e.preventDefault();
            closeMobileSettings();
        }
        // Close settings when clicking its overlay
        else if (e.target.matches('.ddfw-mobile-settings-overlay')) {
            closeMobileSettings();
        }
        // Close settings when clicking on a tab link inside it
        else if (e.target.matches('.ddfw-left-tab-wrapper .ddfw-left-tab')) {
            closeMobileSettings();
        }
    });

    // Conditional fields: delegated so fields of soft-loaded views work without re-binding.
    ddfw(document).on('change', SHOW_FIELDS_SELECTOR, e => {
        ddfwToggleFields(e.target);
    });

    ddfwInitView(document);
    turbo.boot(ddfwInitView);
});
