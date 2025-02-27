/**
 * Javascript containing the utils function of local categories_domains plugin
 */

define([
    'jquery',  
    'local_mentor_core/datatables',
    'local_mentor_core/datatables-buttons',
    'local_mentor_core/datatables-select',
    'local_mentor_core/datatables-checkboxes',
    'local_mentor_core/mentor', 
    'format_edadmin/format_edadmin',
], function ($) {
    var local_categories_domains = {
        /**
         * Create domains DataTable                 
         */
        init: function (entityid,user_can_manage_domains) {
            this.createDomainsTable(entityid,user_can_manage_domains);
            $('#add_domain').on('click', function () {
                this.addDomainPopup();
            });
            
        },
        /**
         * Initial domains table
         * 
         * @param {int} entityid
         */
        createDomainsTable: function (entityid,user_can_manage_domains) {

            var that = this;
            //table course edadmin user admin
            M.table = $('#domains-table').DataTable({
                dom: 'Blfrtip',
                serverSide: true,
                info: false,
                paging: false,
                ajax: {
                    //Call data members cohort
                    url: M.cfg.wwwroot + '/local/categories_domains/ajax/ajax.php',
                    data: function (d) {// GET HTTP data setting
                        d.controller = 'categories_domains';
                        d.action = 'get_categories_domains';
                        d.format = 'json';
                        d.entityid = entityid;
                    }
                },
                oLanguage: {
                    sUrl: M.cfg.wwwroot + '/local/mentor_core/datatables/lang/' + M.util.get_string('langfile', 'local_categories_domains') + ".json"
                },
                columns: [
                    {data: 'domain_name'},
                    {data: 'actions'},
                ],
                //To create header buttons
                dom: 'Bfrtip',
                //Header buttons
                buttons: user_can_manage_domains ? 
                    [{
                        text: M.util.get_string('add_domain', 'local_categories_domains'),
                        className: 'btn btn-primary',
                        attr: {
                            id: 'add_domain',
                        },
                        action: function () {                           
                                that.addDomainPopup();                            
                        }
                    }] : [],
                
            });
        },

        addDomainPopup: function () {
           var warningMessageDisplayClass = 'domain-add-form-warning-none';
           mentor.dialog('#domain-add-popup', {
                width: 600,
                title: M.util.get_string('add_domain', 'local_categories_domains'),
                buttons: [
                    {
                        text: M.util.get_string('confirm', 'local_categories_domains'),
                        id: 'confirm-add-domain',
                        class: "btn-primary",
                        click: function (e) {

                            var that = $(this);
                            var dataFrom = $('#domain-add-form').serializeArray();
                            var entityid = $('#adddomains').attr('data-entity-id');
                            
                             // Check if domain name data is not empty.
                            if (dataFrom[0].value.trim() === '' ) {
                                $('.domain-add-form-warning').removeClass(warningMessageDisplayClass).html(M.util.get_string('requiredfield', 'local_categories_domains'));
                            } else {
                                $('.domain-add-form-warning').addClass(warningMessageDisplayClass).html('');

                                format_edadmin.ajax_call({
                                    url: M.cfg.wwwroot + '/local/categories_domains/ajax/ajax.php',
                                    controller: 'test', //TO DO : add controller and remove the comment !
                                    action: 'test',//TO DO : add method and remove the comment !
                                    format: 'json',
                                    entityid: entityid,
                                    domainname: dataFrom[0].value,
                                    callback: function (response) {
                                        response = JSON.parse(response);
                                        if (response.message === true) {
                                            M.table.ajax.reload();
                                            $('.domain-add-form-warning').addClass(warningMessageDisplayClass).html('');
                                            $('#domain-add-form')[0].reset();
                                            that.dialog("destroy");
                                        } else {
                                            $('.domain-add-form-warning').removeClass(warningMessageDisplayClass).html(warningMessage);
                                        }
                                    }
                                });
                            }
                        }
                    },
                    {
                        // Cancel button
                        text: M.util.get_string('cancel', 'local_categories_domains'),
                        class: "btn-secondary",
                        click: function () {
                            //Just close the modal
                            $('.domain-add-form-warning').addClass(warningMessageDisplayClass).html('');
                            $('#domain-add-form')[0].reset();
                            $(this).dialog("destroy");
                        }
                    }
                ],
                close: function () {
                    //Just close the modal
                    $('.domain-add-form-warning').addClass(warningMessageDisplayClass).html('');
                    $('#domain-add-form')[0].reset();
                    $(this).dialog("destroy");
                },
            });
        }
    };

    //add object to window to be called outside require
    window.local_categories_domains = local_categories_domains;
    return local_categories_domains;
});
