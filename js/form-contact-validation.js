(function (global) {
    'use strict';

    var CONTACT_LENGTH = 11;

    function sanitizeContactInputValue(value) {
        return String(value || '').replace(/\D/g, '').slice(0, CONTACT_LENGTH);
    }

    function isValidContactNumber(value) {
        return /^\d{11}$/.test(String(value || '').trim());
    }

    function isValidContactNumberOptional(value) {
        var trimmed = String(value || '').trim();
        return trimmed === '' || isValidContactNumber(trimmed);
    }

    function bindContactNumberInput(input) {
        if (!input || input.dataset.contactBound === '1') {
            return;
        }

        input.dataset.contactBound = '1';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('maxlength', String(CONTACT_LENGTH));
        input.setAttribute('pattern', '\\d{11}');
        input.setAttribute('autocomplete', 'off');

        if (input.dataset.contactRequired !== 'false') {
            input.setAttribute('minlength', String(CONTACT_LENGTH));
        }

        if (!input.hasAttribute('placeholder')) {
            input.setAttribute('placeholder', '11 digits, numbers only');
        }

        input.addEventListener('input', function () {
            var sanitized = sanitizeContactInputValue(input.value);
            if (input.value !== sanitized) {
                input.value = sanitized;
            }
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();
            var pasted = (event.clipboardData || global.clipboardData).getData('text');
            input.value = sanitizeContactInputValue(pasted);
        });
    }

    function disableFormAutofill(form) {
        if (!form) {
            return;
        }

        form.setAttribute('autocomplete', 'off');
        form.querySelectorAll('input, textarea, select').forEach(function (field) {
            if (field.type === 'password') {
                field.setAttribute('autocomplete', 'new-password');
                return;
            }
            if (field.type === 'hidden' || field.type === 'file') {
                return;
            }
            field.setAttribute('autocomplete', 'off');
        });
    }

    function validateContactInput(input, label) {
        var required = input.dataset.contactRequired !== 'false';
        var value = String(input.value || '').trim();

        if (!required && value === '') {
            return null;
        }

        if (required && !isValidContactNumber(value)) {
            return (label || 'Contact number') + ' must be exactly 11 digits (numbers only).';
        }

        if (!required && !isValidContactNumberOptional(value)) {
            return (label || 'Contact number') + ' must be exactly 11 digits (numbers only) when provided.';
        }

        return null;
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;

        scope.querySelectorAll('form').forEach(disableFormAutofill);
        scope.querySelectorAll('.contact-number-input, input[data-contact-number="true"]').forEach(bindContactNumberInput);
    }

    global.AlertaraFormEnhancements = {
        CONTACT_LENGTH: CONTACT_LENGTH,
        sanitizeContactInputValue: sanitizeContactInputValue,
        isValidContactNumber: isValidContactNumber,
        isValidContactNumberOptional: isValidContactNumberOptional,
        bindContactNumberInput: bindContactNumberInput,
        disableFormAutofill: disableFormAutofill,
        validateContactInput: validateContactInput,
        init: init,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }
})(window);
