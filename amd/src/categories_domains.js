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
    'local_mentor_specialization/common',
], function ($) {
    var local_categories_domains = {
        /**
         * Create domains DataTable                 
         */
        init: function (entityid, user_can_manage_domains, user_is_siteadmin) {
            $('#domain-add-form').on('submit', function (e) {
                e.preventDefault();
                return false;
            });

            this.createDomainsTable(entityid, user_can_manage_domains, user_is_siteadmin);
            $('#add_domain').on('click', function () {
                this.addDomainPopup();
            });
        },
        /**
         * Initial domains table
         * 
         * @param {int} entityid
         */
        createDomainsTable: function (entityid, user_can_manage_domains, user_is_siteadmin) {
            var that = this;
            //table course edadmin user admin
            M.table = $('#domains-table').DataTable({
                dom: 'Blfrtip',
                serverSide: true,
                info: false,
                paging: false,
                order: [],
                ajax: {
                    //Call data members cohort
                    url: M.cfg.wwwroot + '/local/categories_domains/ajax/ajax.php',
                    data: function (d) {// GET HTTP data setting
                        d.controller = 'categories_domains';
                        d.action = 'get_categories_domains';
                        d.format = 'json';
                        d.entityid = entityid;
                        d.search = d.search && d.search.value ? d.search.value : null;
                    }
                },
                oLanguage: {
                    sUrl: M.cfg.wwwroot + '/local/mentor_core/datatables/lang/' + M.util.get_string('langfile', 'local_categories_domains') + ".json"
                },
                columnDefs:  user_can_manage_domains ? [{
                    orderable: false, 
                    targets: 1 
                }] : [],
                columns: user_can_manage_domains ? 
                    [
                        { data: 'domain_name' },
                        {
                            data: 'actions',
                            className: 'domains-table-actions',

                            render: function (data, type, row, meta) {
                                if (data) {
                                    return data;
                                }
                                return '';
                            }

                        },
                    ] : [{ data: 'domain_name' }],
                //To create header buttons
                dom: 'Bfrtip',                
                language: { search: ""},
                search: {return: true},
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
                }].concat(user_is_siteadmin ? [{
                    text: M.util.get_string('import_csv_domain', 'local_categories_domains'),
                    className: 'btn btn-primary',
                    attr: {
                        id: 'import_csv_domain',
                    },
                    action: function () {                           
                        window.location.href = M.cfg.wwwroot + '/local/categories_domains/pages/importcsv.php?entityid='+entityid;                        
                    }
                }] : [])
                : [],
                initComplete: () => { addSearchButton("domains-table_filter") }
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
                            var urlParams = new URLSearchParams(window.location.search);
                            var entityid = urlParams.get('entityid') || 0;

                            // Check if domain name data is not empty.
                            if (dataFrom[0].value.trim() === '') {
                                $('.domain-add-form-warning').removeClass(warningMessageDisplayClass).html(M.util.get_string('requiredfield', 'local_categories_domains'));
                            } else {
                                $('.domain-add-form-warning').addClass(warningMessageDisplayClass).html('');

                                format_edadmin.ajax_call({
                                    url: M.cfg.wwwroot + '/local/categories_domains/ajax/ajax.php',
                                    controller: 'categories_domains',
                                    action: 'add_domain',
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
                                            $('.domain-add-form-warning').removeClass(warningMessageDisplayClass).html(response.message);
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
        },

        deleteDomainPopup: function (domain_name) {
            mentor.dialog('<p>' + M.util.get_string('delete_domain_confirmation_text', 'local_categories_domains', domain_name) + '</p>', {
                width: 600,
                title: M.util.get_string('delete_domain', 'local_categories_domains'),
                buttons: [
                    {
                        text: M.util.get_string('confirm', 'local_categories_domains'),
                        id: 'confirm-delete-domain',
                        class: "btn-primary",
                        click: function (e) {
                            let that = $(this);
                            let entityid = new URLSearchParams(window.location.search).get('entityid');
                            if (entityid) {
                                format_edadmin.ajax_call({
                                    url: M.cfg.wwwroot + '/local/categories_domains/ajax/ajax.php',
                                    controller: 'categories_domains',
                                    action: 'delete_categorie_domain',
                                    format: 'json',
                                    entityid: entityid,
                                    domainname: domain_name,
                                    callback: function (response) {
                                        response = JSON.parse(response);
                                        if (response === true) {
                                            that.dialog("destroy");
                                            M.table.ajax.reload();
                                        } else {
                                            format_edadmin.error_modal(response);
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
                            $(this).dialog("destroy");
                        }
                    }
                ],
                close: function () {
                    //Just close the modal
                    $(this).dialog("destroy");
                },
            });
        },

        triggerExportCSV: function(entityshortname, domains){
            $('#export_csv_domains').on('click', () => {
                this.downloadCSVFile(entityshortname, domains);
                });
        },
        
        downloadCSVFile: function(entityshortname, domains){
            var csv = '';
            var csvFile;
            var downloadLink;
            
            // Add CSV Headers
            var headers = M.util.get_string('headers_export_csv_domains', 'local_categories_domains');
            csv += headers.join(';') + '\n';
            
            // Add CSV data
            for (const domainKey in domains) {
                if (domains.hasOwnProperty(domainKey)) {
                    const item = domains[domainKey];
                    csv += domainKey + ';' + (item.idnumber || '') + '\n';
                }
            }            

            var dateobject = new Date();
            var dateformat = dateobject.getFullYear() + '-' + ('0' + (dateobject.getMonth() + 1)).slice(-2) + '-' + ('0' + dateobject.getDate()).slice(-2);

            var BOM = "\uFEFF";
            var csvData = BOM + csv;

            csvFile = new Blob([csvData], {type: 'text/csv'});
            downloadLink = document.createElement("a");
            downloadLink.download = 'export_csv' + '_' + entityshortname + '_' + dateformat + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";

            document.body.appendChild(downloadLink);
            downloadLink.click();
        }
    };

    //add object to window to be called outside require
    window.local_categories_domains = local_categories_domains;
    return local_categories_domains;
});
