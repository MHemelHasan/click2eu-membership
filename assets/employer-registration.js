/**
 * Click2EU Employer Registration Integration
 *
 * Injects Company Name and Company Registration Number into Cariera's
 * registration form and toggles visibility based on selected user role.
 */
(function($) {
    'use strict';

    function initEmployerFields() {
        var $form = $('form#cariera_registration');
        if (!$form.length || $('#c2eu-employer-fields').length) {
            return;
        }

        var i18n = window.c2eu_reg_i18n || {
            company_name: 'Company Name',
            company_name_placeholder: 'Your Company Name',
            reg_number: 'Company Registration Number',
            reg_number_placeholder: 'e.g. HRB 12345 or Registration ID'
        };

        var fieldsHtml = 
            '<div class="c2eu-employer-fields" id="c2eu-employer-fields" style="display: none; margin-bottom: 1rem;">' +
                '<div class="form-group">' +
                    '<label for="c2eu_company_name">' + i18n.company_name + ' <span class="required" style="color: #e11d48;">*</span></label>' +
                    '<input name="c2eu_company_name" id="c2eu_company_name" class="form-control" type="text" placeholder="' + i18n.company_name_placeholder + '" />' +
                '</div>' +
                '<div class="form-group">' +
                    '<label for="c2eu_company_registration_number">' + i18n.reg_number + ' <span class="required" style="color: #e11d48;">*</span></label>' +
                    '<input name="c2eu_company_registration_number" id="c2eu_company_registration_number" class="form-control" type="text" placeholder="' + i18n.reg_number_placeholder + '" />' +
                '</div>' +
            '</div>';

        // Insert before submit button group
        var $submitGroup = $form.find('#cariera-user-register').closest('.form-group');
        if ($submitGroup.length) {
            $submitGroup.before(fieldsHtml);
        } else {
            $form.append(fieldsHtml);
        }

        function toggleVisibility() {
            var $roleInput = $form.find('input[name="cariera_user_role"]:checked');
            var selectedRole = $roleInput.length ? $roleInput.val() : ( $form.find('select[name="cariera_user_role"]').val() || $form.find('input[name="cariera_user_role"]').val() );
            var $fields = $('#c2eu-employer-fields');
            if (selectedRole === 'employer') {
                $fields.slideDown(200);
            } else {
                $fields.slideUp(200);
            }
        }

        $form.on('change', 'input[name="cariera_user_role"], select[name="cariera_user_role"]', toggleVisibility);
        toggleVisibility();
    }

    $(document).ready(initEmployerFields);
    $(document).on('cariera:popup_opened', initEmployerFields);
})(jQuery);
