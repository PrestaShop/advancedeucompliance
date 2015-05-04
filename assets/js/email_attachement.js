$(document).ready(function(){
    var email_attacher = new EmailAttach();
    email_attacher.init();
});

var EmailAttach = function(){

    this.left_column_checkbox_id = 'input[id^=mail_]';
    this.email_attach_form_id = '#emailAttachementsManager';
    this.right_column_checked_checkboxes = 'input[id^=attach_]:checked';

    this.init = function() {

        var that = this;

        $(this.left_column_checkbox_id).on('click', function() {

            var id_clicked = $(this).attr('id');
            id_clicked = that.cleanLeftColumnId(id_clicked);
            var have_to_check_checkbox = $(this).attr('checked') ? true : false;
            that.selectAllFollowingOptions(id_clicked, have_to_check_checkbox);

            console.log('element clicked');
            console.log(id_clicked);

        });

        $(this.email_attach_form_id).on('submit', function(e){
            // Avoid any other behavior but this one
            e.stopPropagation();
            e.preventDefault();

            var assoc_data_array = [];

            // Loop on all selection to get only the checked ones and pass to the controller
            $(that.right_column_checked_checkboxes).each(function(){
                var full_id = $(this).attr('id');
                // mail id should be at 1 and cms_role_id at 2
                var splitted_id = full_id.split('_');
                var id_mail = splitted_id[1];
                var id_cms_role = splitted_id[2];

                assoc_data_array.push({id_mail: id_mail, id_cms_role: id_cms_role});
            });

            that.submitEmailAttachments($(this).attr('action'), assoc_data_array, $(this).attr('method'));

        });

        console.log('EmailAttach inited !');
    }

    this.cleanLeftColumnId = function(full_id) {
        var splitted_id = full_id.split('_');
        return splitted_id[1];
    }

    this.selectAllFollowingOptions = function(base_id, checked_status) {
        console.log('input[id^=attach_'+base_id+'_]');
        $('input[id^=attach_'+base_id+'_]').each(function(){
            $(this).attr('checked', checked_status);
            console.log('checked an option for you !');
        });
    }

    this.submitEmailAttachments = function(action, params, method) {

        var form = document.createElement("form");
        form.setAttribute("method", method);
        form.setAttribute("action", action);

        var hiddenField = document.createElement("input");
        hiddenField.setAttribute("type", "hidden");
        hiddenField.setAttribute("name", 'AEUC_emailAttachmentsManager');
        form.appendChild(hiddenField);

        hiddenField = document.createElement("input");
        hiddenField.setAttribute("type", "hidden");
        hiddenField.setAttribute("name", 'emails_attach_assoc');
        hiddenField.setAttribute("value", JSON.stringify(params));
        form.appendChild(hiddenField);

        form.submit();
    }
};
