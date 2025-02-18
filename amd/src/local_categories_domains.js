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
        init: function () {
            this.createDomainsTable();
        },
        /**
         * Initial domains table
         */
        createDomainsTable: function () {
            var that = this;
            //table course edadmin user admin
            M.table = $('#domains-table').DataTable({
                // TO DO ! Add ajax call to get data from server
                /* ajax: {
                    //Call data members cohort
                    url: M.cfg.wwwroot  , // + TODO : add ajax file path and remove the comment !
                    data: function (d) {// GET HTTP data setting
                        d.controller = 'test'; // + TODO : add controler and remove the comment !
                        d.action = 'test'; // + TODO : add action and remove the comment !
                        d.format = 'json';
                    },
                    dataSrc: 'message'
                },*/
                oLanguage: {
                    sUrl: M.cfg.wwwroot + '/local/mentor_core/datatables/lang/' + M.util.get_string('langfile', 'local_categories_domains') + ".json"
                },
                columns: [
                    {data: 'name'},
                    {data: 'actions'},
                    
                ],
                //To create header buttons
                dom: 'Bfrtip',
                //Header buttons
                buttons: [
                ]
            });
        }
    };

    //add object to window to be called outside require
    window.local_categories_domains = local_categories_domains;
    return local_categories_domains;
});
