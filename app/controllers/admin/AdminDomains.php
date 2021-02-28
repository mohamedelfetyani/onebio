<?php

namespace Altum\Controllers;

use Altum\Database\Database;
use Altum\Middlewares\Csrf;
use Altum\Middlewares\Authentication;
use Altum\Response;

class AdminDomains extends Controller {

    public function index() {

        Authentication::guard('admin');

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['is_enabled', 'user_id', 'type'], ['host'], ['datetime', 'host']));

        /* Prepare the paginator */
        $total_rows = Database::$database->query("SELECT COUNT(*) AS `total` FROM `domains` WHERE 1 = 1 {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('admin/domains?' . $filters->get_get() . '&page=%d')));

        /* Get the users */
        $domains = [];
        $domains_result = Database::$database->query("
            SELECT
                `domains`.*, `users`.`name` AS `user_name`, `users`.`email` AS `user_email`
            FROM
                `domains`
            LEFT JOIN
                `users` ON `domains`.`user_id` = `users`.`user_id`
            WHERE
                1 = 1
                {$filters->get_sql_where('domains')}
                {$filters->get_sql_order_by('domains')}
            LIMIT
                {$paginator->getSqlOffset()}, {$paginator->getItemsPerPage()}
        ");
        while($row = $domains_result->fetch_object()) {
            $domains[] = $row;
        }

        /* Prepare the pagination view */
        $pagination = (new \Altum\Views\View('partials/pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Delete Modal */
        $view = new \Altum\Views\View('admin/domains/domain_delete_modal', (array) $this);
        \Altum\Event::add_content($view->run(), 'modals');

        /* Main View */
        $data = [
            'domains' => $domains,
            'filters' => $filters,
            'pagination' => $pagination
        ];

        $view = new \Altum\Views\View('admin/domains/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }


    public function read() {

        Authentication::guard('admin');

        $datatable = new \Altum\DataTable();
        $datatable->set_accepted_columns(['domain_id', 'type', 'host', 'date', 'links', 'email', 'name', 'is_enabled']);
        $datatable->process($_POST);

        $result = Database::$database->query("
            SELECT
                `domains`.*,
                `users`.`email`,
                COUNT(`links`.`domain_id`) AS `links`,
                (SELECT COUNT(*) FROM `domains`) AS `total_before_filter`,
                (SELECT COUNT(*) FROM `domains` LEFT JOIN `users` ON `domains` . `user_id` = `users` . `user_id` WHERE `users`.`name` LIKE '%{$datatable->get_search()}%' OR `users`.`email` LIKE '%{$datatable->get_search()}%' OR `domains`.`host` LIKE '%{$datatable->get_search()}%') AS `total_after_filter`
            FROM
                `domains`
            LEFT JOIN
                `links` ON `domains`.`domain_id` = `links`.`domain_id`
            LEFT JOIN
                `users` ON `domains`.`user_id` = `users`.`user_id`
            WHERE 
                `users`.`name` LIKE '%{$datatable->get_search()}%' 
                OR `users`.`email` LIKE '%{$datatable->get_search()}%' 
                OR`domains`.`host` LIKE '%{$datatable->get_search()}%'
            GROUP BY
                `domain_id`
            ORDER BY
                `domains`.`type` DESC,
                `domain_id` ASC,
                " . $datatable->get_order() . "
            LIMIT
                {$datatable->get_start()}, {$datatable->get_length()}
        ");

        $total_before_filter = 0;
        $total_after_filter = 0;

        $data = [];

        while($row = $result->fetch_object()):

            /* Type */
            $row->type =
                $row->type == 1 ?
                    '<span class="badge badge-pill badge-success" data-toggle="tooltip" title="' . $this->language->admin_domains->main->type_global . '"><i class="fa fa-fw fa-globe"></i></span>' :
                    '<span class="badge badge-pill badge-secondary" data-toggle="tooltip" title="' . $this->language->admin_domains->main->type_user . '"><i class="fa fa-fw fa-user"></i></span>';

            /* Email */
            $row->email = '<a href="' . url('admin/user-view/' . $row->user_id) . '"> ' . $row->email . '</a>';

            /* host */
            $host_prepend = '<img src="https://external-content.duckduckgo.com/ip3/' . $row->host . '.ico" class="img-fluid icon-favicon mr-1" />';
            $row->host = $host_prepend . '<a href="' . url('admin/domain-update/' . $row->domain_id) . '">' . $row->host . '</a>';

            /* Links */
            $row->links = '<i class="fa fa-fw fa-sm fa-link text-muted"></i> ' . nr($row->links);

            /* is_enabled badge */
            $row->is_enabled = $row->is_enabled ? '<span class="badge badge-pill badge-success"><i class="fa fa-fw fa-check"></i> ' . $this->language->global->active . '</span>' : '<span class="badge badge-pill badge-warning"><i class="fa fa-fw fa-eye-slash"></i> ' . $this->language->global->disabled . '</span>';

            $row->date = '<span data-toggle="tooltip" title="' . \Altum\Date::get($row->date, 1) . '">' . \Altum\Date::get($row->date, 2) . '</span>';
            $row->actions = include_view(THEME_PATH . 'views/admin/partials/admin_domain_dropdown_button.php', ['id' => $row->domain_id]);

            $data[] = $row;
            $total_before_filter = $row->total_before_filter;
            $total_after_filter = $row->total_after_filter;

        endwhile;

        Response::simple_json([
            'data' => $data,
            'draw' => $datatable->get_draw(),
            'recordsTotal' => $total_before_filter,
            'recordsFiltered' =>  $total_after_filter
        ]);

    }

    public function delete() {

        Authentication::guard();

        $domain_id = (isset($this->params[0])) ? (int) $this->params[0] : false;

        if(!Csrf::check('global_token')) {
            $_SESSION['error'][] = $this->language->global->error_message->invalid_csrf_token;
        }

        if(!$domain = Database::get(['domain_id'], 'domains', ['domain_id' => $domain_id])) {
            redirect('admin/domains');
        }

        if(empty($_SESSION['error'])) {

            /* Get all the available biolinks and iterate over them to delete the stored images */
            $result = Database::$database->query("SELECT `link_id`, `settings` FROM `links` WHERE `domain_id` = {$domain->domain_id} AND `type` = 'biolink' AND `subtype` = 'base'");

            while($row = $result->fetch_object()) {

                $row->settings = json_decode($row->settings);

                /* Delete current avatar */
                if(!empty($row->settings->image) && file_exists(UPLOADS_PATH . 'avatars/' . $row->settings->image)) {
                    unlink(UPLOADS_PATH . 'avatars/' . $row->settings->image);
                }

                /* Delete current background */
                if(is_string($row->settings->background) && !empty($row->settings->background) && file_exists(UPLOADS_PATH . 'backgrounds/' . $row->settings->background)) {
                    unlink(UPLOADS_PATH . 'backgrounds/' . $row->settings->background);
                }

                /* Delete the record from the database */
                Database::$database->query("DELETE FROM `links` WHERE `link_id` = {$row->link_id}");

                /* Clear the cache */
                \Altum\Cache::$adapter->deleteItem('biolink_links_' . $row->link_id);

            }

            /* Delete the domain */
            $this->database->query("DELETE FROM `domains` WHERE `domain_id` = {$domain->domain_id}");

            /* Success message */
            $_SESSION['success'][] = $this->language->admin_domain_delete_modal->success_message;

        }

        redirect('admin/domains');
    }

}
