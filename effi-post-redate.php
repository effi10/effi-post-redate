<?php
/**
 * Plugin Name: effi Post Redate
 * Description: Redistribue les dates de publication des articles publiés de façon progressive entre une date de début et une date de fin. Ajoute un onglet "Redater les articles" dans Outils.
 * Version: 1.0.1
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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_cg_redater_get_taxonomies', [$this, 'ajax_get_taxonomies']);
        add_action('wp_ajax_cg_redater_get_terms', [$this, 'ajax_get_terms']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'tools_page_' . self::SLUG) {
            return;
        }

        wp_register_script('cg-redater-admin', '', [], false, true);
        wp_enqueue_script('cg-redater-admin');

        $data = [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('cg_redater_ajax'),
            'translations' => [
                'noneTaxonomy'       => __('Aucune (toutes les taxonomies)', 'cg-redater'),
                'allTerms'           => __('Tous les termes', 'cg-redater'),
                'loadingTaxonomies'  => __('Chargement des taxonomies…', 'cg-redater'),
                'loadingTerms'       => __('Chargement des termes…', 'cg-redater'),
                'chooseTaxonomy'     => __('Sélectionnez une taxonomie.', 'cg-redater'),
                'noTaxonomy'         => __('Aucune taxonomie disponible.', 'cg-redater'),
                'noTerms'            => __('Aucun terme disponible.', 'cg-redater'),
                'error'              => __('Une erreur est survenue, veuillez réessayer.', 'cg-redater'),
            ],
        ];

        wp_localize_script('cg-redater-admin', 'cgRedaterAjax', $data);
        wp_add_inline_script('cg-redater-admin', $this->get_inline_script());
    }

    private function get_inline_script() {
        return <<<JS
jQuery(function($){
    var settings = window.cgRedaterAjax || {};
    var \$postType = $('#post_type');
    var \$taxonomy = $('#taxonomy');
    var \$term = $('#term_id');

    function resetTermsPlaceholder(message) {
        \$term.html($('<option>', { value: '', text: message || settings.translations.chooseTaxonomy }));
        \$term.prop('disabled', true);
    }

    function setTaxonomyLoading() {
        \$taxonomy.html($('<option>', { value: '', text: settings.translations.loadingTaxonomies }));
        \$taxonomy.prop('disabled', true);
        resetTermsPlaceholder(settings.translations.chooseTaxonomy);
    }

    function handleTaxonomyError(message) {
        \$taxonomy.html($('<option>', { value: '', text: message || settings.translations.error }));
        \$taxonomy.prop('disabled', true);
        resetTermsPlaceholder(settings.translations.chooseTaxonomy);
    }

    function setTermsLoading() {
        \$term.html($('<option>', { value: '', text: settings.translations.loadingTerms }));
        \$term.prop('disabled', true);
    }

    function handleTermsError(message) {
        \$term.html($('<option>', { value: '', text: message || settings.translations.error }));
        \$term.prop('disabled', true);
    }

    function fetchTaxonomies(postType, selectedTaxonomy, selectedTerm) {
        if (!postType) {
            handleTaxonomyError(settings.translations.noTaxonomy);
            return;
        }

        setTaxonomyLoading();

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'cg_redater_get_taxonomies',
                nonce: settings.nonce,
                post_type: postType
            }
        }).done(function(response){
            if (!response || !response.success) {
                handleTaxonomyError(settings.translations.error);
                return;
            }

            var list = response.data && response.data.taxonomies ? response.data.taxonomies : [];
            if (!list.length) {
                handleTaxonomyError(settings.translations.noTaxonomy);
                return;
            }

            var options = '<option value="">' + settings.translations.noneTaxonomy + '</option>';
            var hasSelected = false;

            list.forEach(function(item){
                var value = item.name || '';
                var label = item.label || value;

                if (!value) {
                    return;
                }

                options += '<option value="' + value + '">' + label + '</option>';

                if (selectedTaxonomy && value === selectedTaxonomy) {
                    hasSelected = true;
                }
            });

            \$taxonomy.html(options);
            \$taxonomy.prop('disabled', false);

            if (hasSelected) {
                \$taxonomy.val(selectedTaxonomy);
                fetchTerms(selectedTaxonomy, selectedTerm);
            } else {
                \$taxonomy.val('');
                resetTermsPlaceholder(settings.translations.chooseTaxonomy);
            }
        }).fail(function(){
            handleTaxonomyError(settings.translations.error);
        });
    }

    function fetchTerms(taxonomy, selectedTerm) {
        if (!taxonomy) {
            resetTermsPlaceholder(settings.translations.chooseTaxonomy);
            return;
        }

        setTermsLoading();

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'cg_redater_get_terms',
                nonce: settings.nonce,
                taxonomy: taxonomy
            }
        }).done(function(response){
            if (!response || !response.success) {
                handleTermsError(settings.translations.error);
                return;
            }

            var list = response.data && response.data.terms ? response.data.terms : [];
            var options = '<option value="">' + settings.translations.allTerms + '</option>';

            if (!list.length) {
                options += '<option value="" disabled>' + settings.translations.noTerms + '</option>';
                \$term.html(options);
                \$term.prop('disabled', false);
                return;
            }

            list.forEach(function(item){
                var id = item.id || '';
                var name = item.name || '';
                if (!id) {
                    return;
                }
                options += '<option value="' + id + '">' + name + '</option>';
            });

            \$term.html(options);
            \$term.prop('disabled', false);

            if (selectedTerm) {
                \$term.val(String(selectedTerm));
            }
        }).fail(function(){
            handleTermsError(settings.translations.error);
        });
    }

    var initialTaxonomy = \$taxonomy.data('selected') || '';
    var initialTerm = \$term.data('selected') || '';
    var initialPostType = \$postType.val();

    fetchTaxonomies(initialPostType, initialTaxonomy, initialTerm);

    \$postType.on('change', function(){
        var postType = $(this).val();
        \$taxonomy.data('selected', '');
        \$term.data('selected', '');
        fetchTaxonomies(postType, '', '');
    });

    \$taxonomy.on('change', function(){
        var taxonomy = $(this).val();
        fetchTerms(taxonomy, '');
    });
});
JS;
    }

    public function ajax_get_taxonomies() {
        if ( ! current_user_can(self::CAP) ) {
            wp_send_json_error(['message' => __('Permissions insuffisantes.', 'cg-redater')], 403);
        }

        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'cg_redater_ajax') ) {
            wp_send_json_error(['message' => __('Jeton de sécurité invalide.', 'cg-redater')], 400);
        }

        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';

        if ( ! $post_type ) {
            wp_send_json_error(['message' => __('Type de contenu manquant.', 'cg-redater')], 400);
        }

        $taxonomies = get_object_taxonomies($post_type, 'objects');

        if ( empty($taxonomies) ) {
            wp_send_json_success(['taxonomies' => []]);
        }

        $data = [];

        foreach ($taxonomies as $taxonomy) {
            $data[] = [
                'name'  => $taxonomy->name,
                'label' => $taxonomy->labels->singular_name . ' (' . $taxonomy->name . ')',
            ];
        }

        wp_send_json_success(['taxonomies' => $data]);
    }

    public function ajax_get_terms() {
        if ( ! current_user_can(self::CAP) ) {
            wp_send_json_error(['message' => __('Permissions insuffisantes.', 'cg-redater')], 403);
        }

        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'cg_redater_ajax') ) {
            wp_send_json_error(['message' => __('Jeton de sécurité invalide.', 'cg-redater')], 400);
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';

        if ( ! $taxonomy ) {
            wp_send_json_error(['message' => __('Taxonomie manquante.', 'cg-redater')], 400);
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);

        if ( is_wp_error($terms) || empty($terms) ) {
            wp_send_json_success(['terms' => []]);
        }

        $data = [];

        foreach ($terms as $term) {
            $data[] = [
                'id'   => $term->term_id,
                'name' => $term->name,
            ];
        }

        wp_send_json_success(['terms' => $data]);
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
            'taxonomy'      => '',
            'term_id'       => '',
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
                        <?php
                        $taxonomies = get_object_taxonomies($args['post_type'], 'objects');
                        $selected_taxonomy = $args['taxonomy'];
                        if ( ! array_key_exists($selected_taxonomy, $taxonomies) ) {
                            $selected_taxonomy = '';
                        }
                        ?>
                        <tr>
                            <th scope="row"><label for="taxonomy"><?php esc_html_e('Taxonomie à filtrer', 'cg-redater'); ?></label></th>
                            <td>
                                <select id="taxonomy" name="taxonomy" data-selected="<?php echo esc_attr($selected_taxonomy); ?>">
                                    <option value=""><?php esc_html_e('Aucune (toutes les taxonomies)', 'cg-redater'); ?></option>
                                    <?php
                                    foreach ($taxonomies as $tax) {
                                        printf(
                                            '<option value="%s"%s>%s</option>',
                                            esc_attr($tax->name),
                                            selected($selected_taxonomy, $tax->name, false),
                                            esc_html($tax->labels->singular_name . ' (' . $tax->name . ')')
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Optionnel. Sélectionnez une taxonomie pour restreindre le redatage à un groupe de termes.', 'cg-redater'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="term_id"><?php esc_html_e('Terme ciblé', 'cg-redater'); ?></label></th>
                            <td>
                                <?php
                                $terms_options = '<option value="">' . esc_html__('Tous les termes', 'cg-redater') . '</option>';
                                if ( $selected_taxonomy ) {
                                    $terms = get_terms([
                                        'taxonomy'   => $selected_taxonomy,
                                        'hide_empty' => false,
                                    ]);
                                    if ( ! is_wp_error($terms) ) {
                                        foreach ($terms as $term) {
                                            $terms_options .= sprintf(
                                                '<option value="%d"%s>%s</option>',
                                                intval($term->term_id),
                                                selected(intval($args['term_id']), intval($term->term_id), false),
                                                esc_html($term->name)
                                            );
                                        }
                                    }
                                }
                                ?>
                                <select id="term_id" name="term_id" data-selected="<?php echo esc_attr(intval($args['term_id'])); ?>" <?php disabled(empty($selected_taxonomy)); ?>>
                                    <?php echo $terms_options; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Optionnel. Laissez "Tous les termes" pour cibler la taxonomie entière.', 'cg-redater'); ?></p>
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
        $taxonomy   = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $term_id    = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $order_src  = (isset($_POST['order_source']) && in_array($_POST['order_source'], ['asc','desc'], true)) ? $_POST['order_source'] : 'asc';
        $exclude_sticky = ! empty($_POST['exclude_sticky']);
        $limit      = isset($_POST['limit']) && $_POST['limit'] !== '' ? max(1, intval($_POST['limit'])) : 0;
        $dry_run    = ! empty($_POST['dry_run']);

        // Validation taxonomie et terme
        $available_taxonomies = get_object_taxonomies($post_type, 'names');
        if ( ! in_array($taxonomy, $available_taxonomies, true) ) {
            $taxonomy = '';
            $term_id = 0;
        } elseif ( $term_id ) {
            $term = get_term_by('id', $term_id, $taxonomy);
            if ( ! $term || is_wp_error($term) ) {
                $term_id = 0;
            }
        }

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

        if ( $taxonomy && $term_id ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ],
            ];
        } elseif ( $taxonomy ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'operator' => 'EXISTS',
                ],
            ];
        }

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
