/**
 * Javascript containing the utils function of local categories_domains plugin
 */

define([
    'jquery',
    'local_mentor_core/datatables',
    'local_mentor_core/datatables-buttons',
    'local_mentor_core/datatables-select',
    'local_mentor_core/datatables-checkboxes'
], function ($) {
    var local_categories_domains = {
        /**
         * Create domains DataTable                 
         */
        init: function (entityid) {
            this.createDomainsTable(entityid);
        },
        /**
         * Initial domains table
         * 
         * @param {int} entityid
         */
        createDomainsTable: function (entityid) {
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
            });
        }
    };

    //add object to window to be called outside require
    window.local_categories_domains = local_categories_domains;
    return local_categories_domains;
});
