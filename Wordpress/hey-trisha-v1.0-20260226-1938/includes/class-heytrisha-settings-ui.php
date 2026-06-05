<?php
/**
 * HeyTrisha Settings UI
 * Renders the specification / schema upload interface.
 */

class HeyTrisha_Settings_UI {

    public static function render_schema_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $schema_manager    = HeyTrisha_Schema_Manager::get_instance();
        $schema            = $schema_manager->get_schema();
        $schema_uploaded   = get_option( 'heytrisha_schema_uploaded', false );
        $upload_date       = get_option( 'heytrisha_schema_upload_date', '' );
        $tables_count      = get_option( 'heytrisha_schema_tables_count', 0 );
        $spec_active       = $schema_manager->is_specification_active();
        $spec_version      = get_option( 'heytrisha_spec_version', '' );
        $spec_date         = get_option( 'heytrisha_spec_upload_date', $upload_date );
        $rules_summary     = get_option( 'heytrisha_spec_rules_summary', '' );
        $ingest_error      = get_option( 'heytrisha_spec_ingest_error', '' );
        $has_raw           = ( $schema_manager->get_raw_specification() !== null );
        $nonce             = wp_create_nonce( 'heytrisha_schema_nonce' );
        ?>
        <div class="heytrisha-schema-section" style="background:#fff;border:1px solid #ccc;border-radius:5px;padding:20px;margin:20px 0;box-shadow:0 1px 3px rgba(0,0,0,.1);">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-portfolio" style="vertical-align:text-bottom;margin-right:6px;" aria-hidden="true"></span> Knowledge &amp; Schema Specification</h2>
            <p style="color:#555;margin-bottom:6px;">
                Upload any text file describing your data — plain English, CSV, SQL, or JSON.
                The AI will read the file, understand the structure and business rules in it, and use
                <em>only</em> what the file specifies when answering chat questions.
            </p>
            <p style="color:#888;font-size:13px;margin-top:0;">
                Accepted formats: <strong>.txt, .md, .csv, .log, .sql, .json</strong> — max 5 MB.
            </p>

            <?php if ( $spec_active ) : ?>
                <div class="notice notice-success inline" style="margin:15px 0;">
                    <p>
                        <strong><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;margin-right:4px;" aria-hidden="true"></span> Specification Active</strong> — the AI is constrained to file contents.<br>
                        <?php if ( $spec_date ) : ?>
                            <strong>Indexed:</strong> <?php echo esc_html( $spec_date ); ?><br>
                        <?php endif; ?>
                        <?php if ( $spec_version ) : ?>
                            <strong>Version:</strong> <code><?php echo esc_html( substr( $spec_version, 0, 12 ) ); ?>&hellip;</code><br>
                        <?php endif; ?>
                        <?php if ( $tables_count ) : ?>
                            <strong>Structured tables detected:</strong> <?php echo esc_html( $tables_count ); ?><br>
                        <?php endif; ?>
                        <?php if ( $rules_summary ) : ?>
                            <strong>Rules summary:</strong> <?php echo esc_html( $rules_summary ); ?>
                        <?php endif; ?>
                    </p>
                </div>

            <?php elseif ( $has_raw && $ingest_error ) : ?>
                <div class="notice notice-warning inline" style="margin:15px 0;">
                    <p>
                        <strong><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;margin-right:4px;" aria-hidden="true"></span> File saved, but AI indexing failed.</strong><br>
                        Chat will use the full database until indexing succeeds.<br>
                        <strong>Error:</strong> <?php echo esc_html( $ingest_error ); ?><br>
                        Re-upload the file once the API connection is confirmed.
                    </p>
                </div>

            <?php elseif ( $schema_uploaded ) : ?>
                <div class="notice notice-info inline" style="margin:15px 0;">
                    <p>
                        <strong><span class="dashicons dashicons-chart-bar" style="vertical-align:text-bottom;margin-right:4px;" aria-hidden="true"></span> Structured schema stored</strong> (<?php echo esc_html( $tables_count ); ?> tables).<br>
                        <strong>Uploaded:</strong> <?php echo esc_html( $upload_date ); ?><br>
                        AI indexing has not run yet — re-upload to activate specification mode.
                    </p>
                </div>

            <?php else : ?>
                <div class="notice notice-info inline" style="margin:15px 0;">
                    <p>No specification uploaded yet. Upload a file or generate a schema snapshot from your live database.</p>
                </div>
            <?php endif; ?>

            <div class="schema-actions" style="margin:20px 0;">
                <button type="button" class="button button-primary" id="heytrisha-upload-schema-btn" style="margin-right:10px;display:inline-flex;align-items:center;gap:6px;vertical-align:middle;">
                    <span class="dashicons dashicons-upload" style="vertical-align:text-bottom;margin-top:3px;" aria-hidden="true"></span>
                    Upload Specification File
                </button>
                <button type="button" class="button" id="heytrisha-generate-schema-btn" style="margin-right:10px;display:inline-flex;align-items:center;gap:6px;vertical-align:middle;">
                    <span class="dashicons dashicons-update" style="vertical-align:text-bottom;margin-top:3px;" aria-hidden="true"></span>
                    Generate from Database
                </button>
                <?php if ( $schema_uploaded || $has_raw ) : ?>
                    <button type="button" class="button button-link-delete" id="heytrisha-delete-schema-btn" style="display:inline-flex;align-items:center;gap:6px;vertical-align:middle;">
                        <span class="dashicons dashicons-trash" style="vertical-align:text-bottom;margin-top:3px;" aria-hidden="true"></span>
                        Delete Specification
                    </button>
                <?php endif; ?>
            </div>

            <input type="file" id="heytrisha-schema-file" style="display:none;"
                   accept=".json,.txt,.sql,.md,.csv,.log">

            <div id="heytrisha-upload-status" style="display:none;margin:10px 0;padding:10px;border-radius:4px;"></div>

            <?php if ( $schema ) : ?>
                <div style="margin-top:30px;">
                    <h3><span class="dashicons dashicons-editor-table" style="vertical-align:text-bottom;margin-right:6px;" aria-hidden="true"></span> Structured Schema Preview</h3>
                    <div id="schema-preview" style="background:#f5f5f5;padding:15px;border-radius:5px;max-height:400px;overflow-y:auto;border:1px solid #ddd;">
                        <pre style="margin:0;font-size:12px;line-height:1.5;"><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
                    </div>
                </div>
            <?php elseif ( $has_raw ) : ?>
                <div style="margin-top:30px;">
                    <h3><span class="dashicons dashicons-media-text" style="vertical-align:text-bottom;margin-right:6px;" aria-hidden="true"></span> Raw Specification Preview (first 800 chars)</h3>
                    <div style="background:#f5f5f5;padding:15px;border-radius:5px;max-height:300px;overflow-y:auto;border:1px solid #ddd;">
                        <pre style="margin:0;font-size:12px;line-height:1.5;"><?php echo esc_html( substr( $schema_manager->get_raw_specification(), 0, 800 ) ); ?>&hellip;</pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( $nonce ); ?>';

            function heytrishaNotifySchemaUpdated(revision) {
                var rev = revision || Date.now();
                try {
                    localStorage.setItem('heytrisha_schema_revision', String(rev));
                } catch (e) {}
                try {
                    var bc = new BroadcastChannel('heytrisha-schema-sync');
                    bc.postMessage({ type: 'schema-updated', revision: rev });
                    bc.close();
                } catch (e) {}
                try {
                    window.dispatchEvent(new CustomEvent('heytrisha-schema-updated', { bubbles: true, detail: { revision: rev } }));
                } catch (e) {}
            }

            function heytrishaRefreshSchemaPreview() {
                $.post(ajaxurl, { action: 'heytrisha_get_schema', nonce: nonce }, function(r) {
                    if (!r || !r.success) return;
                    var d = r.data;
                    if (!d.schema) {
                        $('#schema-preview').closest('div').parent().remove();
                        return;
                    }
                    var json = JSON.stringify(d.schema, null, 2);
                    if ($('#schema-preview pre').length) {
                        $('#schema-preview pre').text(json);
                    } else {
                        location.reload();
                    }
                });
            }

            function showStatus(msg, type) {
                var $el = $('#heytrisha-upload-status');
                var bg  = type === 'success' ? '#d4edda' : (type === 'warning' ? '#fff3cd' : '#f8d7da');
                var col = type === 'success' ? '#155724' : (type === 'warning' ? '#856404' : '#721c24');
                $el.css({ background: bg, color: col, border: '1px solid ' + col }).html(msg).show();
            }

            // Upload button click
            $('#heytrisha-upload-schema-btn').on('click', function() {
                $('#heytrisha-schema-file').click();
            });

            // File selected
            $('#heytrisha-schema-file').on('change', function() {
                var file = this.files[0];
                if (!file) return;

                showStatus('<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Uploading and indexing specification… this may take a moment.', 'info');
                $('#heytrisha-upload-schema-btn').prop('disabled', true);

                var formData = new FormData();
                formData.append('action', 'heytrisha_upload_schema');
                formData.append('nonce', nonce);
                formData.append('schema_file', file);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#heytrisha-upload-schema-btn').prop('disabled', false);
                        if (response.success) {
                            var d = response.data;
                            var status = d.ingest_status === 'ok' ? 'success' : 'warning';
                            showStatus(d.message, status);
                            heytrishaNotifySchemaUpdated(d.schema_revision);
                            heytrishaRefreshSchemaPreview();
                        } else {
                            showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        $('#heytrisha-upload-schema-btn').prop('disabled', false);
                        showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Upload failed — check the browser console for details.', 'error');
                    }
                });

                // Reset input so the same file can be re-uploaded
                this.value = '';
            });

            // Generate schema button
            $('#heytrisha-generate-schema-btn').on('click', function() {
                if (!confirm('Generate a schema snapshot from your WordPress database?')) return;

                showStatus('<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Generating schema…', 'info');
                $(this).prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'heytrisha_generate_schema', nonce: nonce },
                    success: function(response) {
                        $('#heytrisha-generate-schema-btn').prop('disabled', false);
                        if (response.success) {
                            showStatus('<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Schema generated (' + response.data.tables + ' tables). Chatbot updated.', 'success');
                            heytrishaNotifySchemaUpdated(response.data.schema_revision);
                            heytrishaRefreshSchemaPreview();
                        } else {
                            showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        $('#heytrisha-generate-schema-btn').prop('disabled', false);
                        showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Generation failed.', 'error');
                    }
                });
            });

            // Delete schema button
            $('#heytrisha-delete-schema-btn').on('click', function() {
                if (!confirm('Delete the uploaded specification? The AI will revert to using the full live database.')) return;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'heytrisha_delete_schema', nonce: nonce },
                    success: function(response) {
                        if (response.success) {
                            showStatus('<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Specification deleted. Chatbot updated.', 'success');
                            heytrishaNotifySchemaUpdated(response.data.schema_revision);
                            $('#schema-preview').closest('div').parent().remove();
                        } else {
                            showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        showStatus('<span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-right:6px;" aria-hidden="true"></span> Deletion failed.', 'error');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
