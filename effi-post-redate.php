<?php
/**
 * Plugin Name: effi Post Redate
 * Description: Redistribue les dates de publication des articles publiés de façon progressive entre une date de début et une date de fin. Ajoute un onglet "Redater les articles" dans Outils.
 * Version: 1.0.0
 * Author: Cédric GIRARD
 * Author URI: https://www.effi10.com
 * License: GPLv2 or later
 */

if ( ! defined('ABSPATH') ) exit;

class CG_Redater_Articles {
    const SLUG = 'cg-redater-articles';
    const CAP  = 'edit_others_posts'; // ou 'manage_options' si vous préférez restreindre

    public function __construct() {
        add_action('admin_menu', [$this, 'add_tools_page']);
        add_action('admin_init', [$this, 'handle_form']);
    }

    public function add_tools_page() {
        add_management_page(
            __('Redater les articles', 'cg-redater'),
            __('Redater les articles', 'cg-redater'),
            self::CAP,
            self::SLUG,
            [$this, 'render_page']
        );
    }

    private function get_today_site_date() {
        $ts = current_time('timestamp'); // timezone du site
        return date('Y-m-d', $ts);
    }

    public function render_page() {
        if ( ! current_user_can(self::CAP) ) {
            wp_die(__('Permissions insuffisantes.', 'cg-redater'));
        }

        // valeurs par défaut
        $defaults = [
            'date_start'    => '',
            'date_end'      => $this->get_today_site_date(),
            'post_type'     => 'post',
            'order_source'  => 'asc',   // comment on lit l’ordre des articles
            'exclude_sticky'=> '1',
            'limit'         => '',
            'dry_run'       => '1',     // mode test par défaut
        ];

        $args = wp_parse_args($_POST, $defaults);
        $result = get_transient('cg_redater_result'); // affiche le dernier résultat après POST/redirect

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Redater les articles', 'cg-redater'); ?></h1>
            <p><?php esc_html_e('Redistribuez les dates de publication de vos articles publiés entre deux dates, de façon progressive et ordonnée.', 'cg-redater'); ?></p>

            <?php if ( $result ) : ?>
                <div class="notice notice-<?php echo esc_attr($result['type']); ?> is-dismissible">
                    <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                    <?php if (!empty($result['details'])): ?>
                        <p><?php echo wp_kses_post($result['details']); ?></p>
                    <?php endif; ?>
                </div>
                <?php delete_transient('cg_redater_result'); ?>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('cg_redater_nonce', 'cg_redater_nonce_field'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="date_start"><?php esc_html_e('Date de début', 'cg-redater'); ?></label></th>
                            <td><input type="date" id="date_start" name="date_start" value="<?php echo esc_attr($args['date_start']); ?>" placeholder="YYYY-MM-DD" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="date_end"><?php esc_html_e('Date de fin', 'cg-redater'); ?></label></th>
                            <td><input type="date" id="date_end" name="date_end" value="<?php echo esc_attr($args['date_end']); ?>" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="post_type"><?php esc_html_e('Type de contenu', 'cg-redater'); ?></label></th>
                            <td>
                                <select id="post_type" name="post_type">
                                    <?php
                                    $pts = get_post_types(['public' => true], 'objects');
                                    foreach ($pts as $pt) {
                                        printf(
                                            '<option value="%s"%s>%s</option>',
                                            esc_attr($pt->name),
                                            selected($args['post_type'], $pt->name, false),
                                            esc_html($pt->labels->singular_name . ' (' . $pt->name . ')')
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Par défaut : articles (post).', 'cg-redater'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Ordre de référence des articles', 'cg-redater'); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="order_source" value="asc" <?php checked($args['order_source'], 'asc'); ?> /> <?php esc_html_e('Du plus ancien au plus récent (conserve la chronologie relative)', 'cg-redater'); ?></label><br/>
                                    <label><input type="radio" name="order_source" value="desc" <?php checked($args['order_source'], 'desc'); ?> /> <?php esc_html_e('Du plus récent au plus ancien', 'cg-redater'); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Exclure les articles épinglés', 'cg-redater'); ?></th>
                            <td>
                                <label><input type="checkbox" name="exclude_sticky" value="1" <?php checked($args['exclude_sticky'], '1'); ?> /> <?php esc_html_e('Oui', 'cg-redater'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="limit"><?php esc_html_e('Limiter au nombre d’articles', 'cg-redater'); ?></label></th>
                            <td>
                                <input type="number" id="limit" name="limit" min="1" step="1" value="<?php echo esc_attr($args['limit']); ?>" />
                                <p class="description"><?php esc_html_e('Optionnel. Laisse vide pour traiter tous les articles publiés.', 'cg-redater'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Mode test (aperçu)', 'cg-redater'); ?></th>
                            <td>
                                <label><input type="checkbox" name="dry_run" value="1" <?php checked($args['dry_run'], '1'); ?> /> <?php esc_html_e('Ne pas enregistrer, montrer seulement l’aperçu', 'cg-redater'); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Calculer et (si désactivé le mode test) appliquer', 'cg-redater')); ?>
            </form>

            <hr/>
            <p><em><?php esc_html_e('Astuce : en mode test, un échantillon des premières attributions de dates est affiché pour contrôle.', 'cg-redater'); ?></em></p>
        </div>
        <?php
    }

    public function handle_form() {
        if ( empty($_POST) ) return;
        if ( ! isset($_POST['cg_redater_nonce_field']) || ! wp_verify_nonce($_POST['cg_redater_nonce_field'], 'cg_redater_nonce') ) return;
        if ( ! current_user_can(self::CAP) ) return;

        $date_start = isset($_POST['date_start']) && $_POST['date_start'] !== '' ? sanitize_text_field($_POST['date_start']) : '';
        $date_end   = isset($_POST['date_end']) ? sanitize_text_field($_POST['date_end']) : '';
        $post_type  = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'post';
        $order_src  = (isset($_POST['order_source']) && in_array($_POST['order_source'], ['asc','desc'], true)) ? $_POST['order_source'] : 'asc';
        $exclude_sticky = ! empty($_POST['exclude_sticky']);
        $limit      = isset($_POST['limit']) && $_POST['limit'] !== '' ? max(1, intval($_POST['limit'])) : 0;
        $dry_run    = ! empty($_POST['dry_run']);

        // Validation dates
        $tz = wp_timezone();
        $start_ts = $date_start ? strtotime($date_start . ' 00:00:00', 0) : null;
        $end_ts   = strtotime($date_end . ' 23:59:59', 0);

        if ( ! $end_ts ) {
            $this->notify('error', __('La date de fin est invalide.', 'cg-redater'));
            return;
        }
        if ( $date_start && $start_ts && $start_ts > $end_ts ) {
            $this->notify('error', __('La date de début doit être antérieure ou égale à la date de fin.', 'cg-redater'));
            return;
        }

        // Récupération des posts
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit ? $limit : -1,
            'orderby'        => 'date',
            'order'          => $order_src === 'asc' ? 'ASC' : 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        if ( $exclude_sticky ) {
            $sticky = get_option('sticky_posts', []);
            if ( ! empty($sticky) ) {
                $args['post__not_in'] = array_map('intval', $sticky);
            }
        }

        $q = new WP_Query($args);
        $ids = $q->posts;

        if ( empty($ids) ) {
            $this->notify('warning', __('Aucun article trouvé avec ces critères.', 'cg-redater'));
            return;
        }

        $count = count($ids);

        // Détermination des bornes
        if ( ! $start_ts ) {
            // Si pas de date de début fournie, on prend la date du plus ancien (ou plus récent) comme borne opposée
            // pour garder un étalement naturel. Sinon, on part du même jour que la fin pour un seul article.
            if ( $count > 1 ) {
                // Par défaut, si non fourni : étaler depuis (fin - (count-1) jours) pour donner une progression jour par jour
                $start_ts = strtotime(date('Y-m-d', $end_ts) . ' 00:00:00') - ( ($count - 1) * DAY_IN_SECONDS );
            } else {
                $start_ts = strtotime(date('Y-m-d', $end_ts) . ' 00:00:00');
            }
            $date_start = date('Y-m-d', $start_ts);
        }

        // Calcul des intervalles
        $span = max(0, $end_ts - $start_ts);
        $step = ($count > 1) ? floor($span / ($count - 1)) : 0;

        // Attribution progressive
        $preview = [];
        $updated = 0;

        // Sécurité
        @set_time_limit(0);

        foreach ($ids as $i => $post_id) {
            $assigned_ts = $start_ts + ($step * $i);
            if ( $assigned_ts > $end_ts ) $assigned_ts = $end_ts;

            // post_date en timezone du site
            $post_date_local = wp_date('Y-m-d H:i:s', $assigned_ts, $tz);
            // Convertir en GMT selon WP
            $post_date_gmt   = get_gmt_from_date($post_date_local);

            if ( $dry_run ) {
                if ($i < 10) {
                    $preview[] = sprintf(
                        '#%d → %s',
                        intval($post_id),
                        esc_html($post_date_local)
                    );
                }
            } else {
                $res = wp_update_post([
                    'ID'            => $post_id,
                    'post_date'     => $post_date_local,
                    'post_date_gmt' => $post_date_gmt,
                ], true);

                if ( ! is_wp_error($res) ) {
                    $updated++;
                }
            }
        }

        if ( $dry_run ) {
            $details  = '<strong>' . sprintf( esc_html__('Aperçu (10 premiers sur %d) :', 'cg-redater'), $count ) . '</strong><br/>';
            $details .= '<code>' . implode("</code><br/><code>", array_map('esc_html', $preview)) . '</code>';
            $details .= '<br/><br/>';
            $details .= sprintf(
                esc_html__('Étendue: du %s 00:00:00 au %s 23:59:59 | Intervalle moyen: %s secondes | Articles ciblés: %d', 'cg-redater'),
                esc_html($date_start), esc_html($date_end), esc_html($step), intval($count)
            );
            $this->notify('info', __('Mode test : aucune modification enregistrée.', 'cg-redater'), $details);
        } else {
            $details = sprintf(
                esc_html__('Nouveau planning appliqué de %s à %s. Articles mis à jour: %d/%d.', 'cg-redater'),
                esc_html($date_start), esc_html($date_end), intval($updated), intval($count)
            );
            $this->notify('success', __('Redatage terminé.', 'cg-redater'), $details);
        }

        // Redirection douce pour éviter le resoumission rapide
        wp_safe_redirect( add_query_arg(['page' => self::SLUG], admin_url('tools.php')) );
        exit;
    }

    private function notify($type, $message, $details = '') {
        set_transient('cg_redater_result', [
            'type'    => $type, // success | info | warning | error
            'message' => $message,
            'details' => $details,
        ], 60);
    }
}

new CG_Redater_Articles();
