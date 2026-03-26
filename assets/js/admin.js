/**
 * EIU Research Publication – Admin JS
 * Version: 2.0.2
 */
/* global eiuRPAdmin, jQuery */
(function( $ ) {
    'use strict';

    const { ajaxUrl, nonce, deleteNonce, bulkNonce, i18n } = eiuRPAdmin;

    // ── Helper: AJAX wrapper ────────────────────────────────────────
    function eiu_ajax( action, data, $btn, successCb, errorCb ) {
        const orig = $btn ? $btn.text() : '';
        if ( $btn ) { $btn.prop( 'disabled', true ).text( '…' ); }

        $.post( ajaxUrl, Object.assign( { action, nonce }, data ) )
            .done(function( res ) {
                if ( res.success ) {
                    successCb && successCb( res );
                } else {
                    errorCb && errorCb( res );
                    alert( res.data && res.data.message ? res.data.message : i18n.error );
                }
            })
            .fail(function() {
                alert( i18n.error );
            })
            .always(function() {
                if ( $btn ) { $btn.prop( 'disabled', false ).text( orig ); }
            });
    }

    // ── Inline message helper ───────────────────────────────────────
    function show_msg( $el, msg, type ) {
        $el.removeClass( 'success error' ).addClass( type ).text( msg ).show();
        setTimeout( () => $el.fadeOut(), 5000 );
    }

    // ── Update article status (with optional backdating) ────────────
    $( document ).on( 'change', '#eiu-article-status-select', function() {
        const val = $( this ).val();
        $( '#eiu-publish-date-wrap' ).toggle( val === 'published' );
    });

    $( document ).on( 'click', '#eiu-update-status-btn', function() {
        const $btn         = $( this );
        const id           = $btn.data( 'article-id' );
        const status       = $( '#eiu-article-status-select' ).val();
        const published_at = $( '#eiu-publish-date' ).val() || '';

        eiu_ajax( 'eiu_rp_admin_update_article_status', { article_id: id, status, published_at }, $btn,
            function( res ) {
                show_msg( $( '#eiu-status-msg' ), res.data.message, 'success' );
            },
            function( res ) {
                show_msg( $( '#eiu-status-msg' ), res.data.message, 'error' );
            }
        );
    });

    // ── Assign reviewer ─────────────────────────────────────────────
    $( document ).on( 'click', '#eiu-assign-reviewer-btn', function() {
        const $btn       = $( this );
        const article_id = $btn.data( 'article-id' );
        const reviewer_id= $( '#eiu-assign-reviewer-select' ).val();
        const due_date   = $( '#eiu-review-due-date' ).val();

        if ( ! reviewer_id ) { alert( i18n.confirm_assign ); return; }
        if ( ! confirm( i18n.confirm_assign ) ) return;

        eiu_ajax( 'eiu_rp_admin_assign_reviewer', { article_id, reviewer_id, due_date }, $btn,
            function( res ) {
                show_msg( $( '#eiu-assign-msg' ), res.data.message, 'success' );
                setTimeout( () => location.reload(), 1200 );
            },
            function( res ) {
                show_msg( $( '#eiu-assign-msg' ), res.data.message, 'error' );
            }
        );
    });

    // ── Approve review ──────────────────────────────────────────────
    $( document ).on( 'click', '.eiu-btn-approve-review', function() {
        const $btn = $( this );
        const id   = $btn.data( 'id' );
        eiu_ajax( 'eiu_rp_admin_moderate_review', { review_id: id, status: 'approved' }, $btn,
            function() { location.reload(); }
        );
    });

    // ── Reject review ───────────────────────────────────────────────
    $( document ).on( 'click', '.eiu-btn-reject-review', function() {
        const $btn  = $( this );
        const id    = $btn.data( 'id' );
        const notes = prompt( 'Reason for rejection (optional):' ) || '';
        eiu_ajax( 'eiu_rp_admin_moderate_review', { review_id: id, status: 'rejected', admin_notes: notes }, $btn,
            function() { location.reload(); }
        );
    });

    // ── Delete review ───────────────────────────────────────────────
    $( document ).on( 'click', '.eiu-btn-delete-review', function() {
        if ( ! confirm( i18n.confirm_delete ) ) return;
        const $btn = $( this );
        const id   = $btn.data( 'id' );
        eiu_ajax( 'eiu_rp_admin_delete_review', { review_id: id }, $btn,
            function() {
                $btn.closest( 'tr' ).fadeOut( 300, function() { $( this ).remove(); });
            }
        );
    });

    // ── Delete article — list row (Main Admin only) ──────────────────
    $( document ).on( 'click', '.eiu-btn-delete-article', function() {
        const $btn  = $( this );
        const title = $btn.data( 'article-title' ) || 'this article';
        const id    = parseInt( $btn.data( 'article-id' ), 10 );

        if ( ! id || ! confirm( 'Permanently delete "' + title + '"?\n\nThis will remove the article, its uploaded file, and all data. This cannot be undone.' ) ) {
            return;
        }

        $btn.prop( 'disabled', true );

        $.post( ajaxUrl, {
            action:       'eiu_rp_admin_delete_article',
            _ajax_nonce:  deleteNonce || nonce,
            article_id:   id
        })
        .done( function( res ) {
            if ( res && res.success ) {
                $btn.closest( 'tr' ).fadeOut( 400, function() {
                    $( this ).remove();
                    updateBulkCount();
                });
            } else {
                const msg = ( res && res.data && res.data.message ) ? res.data.message : i18n.error;
                alert( 'Delete failed: ' + msg );
                $btn.prop( 'disabled', false );
            }
        })
        .fail( function( xhr ) {
            var msg = 'Delete failed (HTTP ' + xhr.status + ').';
            try { var r = JSON.parse( xhr.responseText ); if ( r && r.data && r.data.message ) msg = r.data.message; } catch(e){}
            alert( msg );
            $btn.prop( 'disabled', false );
        });
    });

    // ── Delete article — sidebar button on article detail page ───────
    $( document ).on( 'click', '#eiu-delete-article-btn', function() {
        const $btn  = $( this );
        const title = $btn.data( 'article-title' ) || 'this article';
        const id    = parseInt( $btn.data( 'article-id' ), 10 );

        if ( ! id || ! confirm( 'Permanently delete "' + title + '"?\n\nThis will remove the article, its uploaded file, and all data. This CANNOT be undone.' ) ) {
            return;
        }

        $btn.prop( 'disabled', true ).text( 'Deleting\u2026' );

        $.post( ajaxUrl, {
            action:       'eiu_rp_admin_delete_article',
            _ajax_nonce:  deleteNonce || nonce,
            article_id:   id
        })
        .done( function( res ) {
            if ( res && res.success ) {
                show_msg( $( '#eiu-delete-msg' ), ( res.data && res.data.message ) || 'Deleted.', 'success' );
                setTimeout( function() { window.location.href = 'admin.php?page=eiu-rp-articles'; }, 1500 );
            } else {
                const msg = ( res && res.data && res.data.message ) ? res.data.message : i18n.error;
                alert( 'Delete failed: ' + msg );
                $btn.prop( 'disabled', false ).text( 'Delete Article Permanently' );
            }
        })
        .fail( function( xhr ) {
            var msg = 'Delete failed (HTTP ' + xhr.status + ').';
            try { var r = JSON.parse( xhr.responseText ); if ( r && r.data && r.data.message ) msg = r.data.message; } catch(e){}
            alert( msg );
            $btn.prop( 'disabled', false ).text( 'Delete Article Permanently' );
        });
    });

    // ── Bulk delete — checkbox + toolbar ────────────────────────────
    function updateBulkCount() {
        const checked = $( '.eiu-article-cb:checked' ).length;
        const $bar    = $( '#eiu-bulk-bar' );
        if ( checked > 0 ) {
            $bar.css( 'display', 'flex' );
            $( '#eiu-bulk-count' ).text( checked + ' article' + ( checked > 1 ? 's' : '' ) + ' selected' );
        } else {
            $bar.hide();
            $( '#eiu-bulk-msg' ).text( '' );
        }
    }

    // Select-all checkbox
    $( document ).on( 'change', '#eiu-select-all', function() {
        $( '.eiu-article-cb' ).prop( 'checked', this.checked );
        updateBulkCount();
    });

    // Individual checkboxes
    $( document ).on( 'change', '.eiu-article-cb', function() {
        const total   = $( '.eiu-article-cb' ).length;
        const checked = $( '.eiu-article-cb:checked' ).length;
        $( '#eiu-select-all' ).prop( 'checked', total > 0 && checked === total )
                              .prop( 'indeterminate', checked > 0 && checked < total );
        updateBulkCount();
    });

    // Deselect all
    $( document ).on( 'click', '#eiu-bulk-cancel-btn', function() {
        $( '.eiu-article-cb, #eiu-select-all' ).prop( 'checked', false ).prop( 'indeterminate', false );
        updateBulkCount();
    });

    // Bulk delete submit
    $( document ).on( 'click', '#eiu-bulk-delete-btn', function() {
        const ids = $( '.eiu-article-cb:checked' ).map( function(){ return $( this ).val(); }).get();
        if ( ids.length === 0 ) { alert( 'No articles selected.' ); return; }
        if ( ! confirm( 'Permanently delete ' + ids.length + ' selected article' + ( ids.length > 1 ? 's' : '' ) + '?\n\nThis action cannot be undone.' ) ) return;

        const $btn = $( this );
        $btn.prop( 'disabled', true ).text( 'Deleting\u2026' );
        $( '#eiu-bulk-msg' ).text( '' ).removeClass( 'success error' );

        $.post( ajaxUrl, { action: 'eiu_rp_admin_bulk_delete_articles', _ajax_nonce: bulkNonce || nonce, article_ids: ids } )
            .done( function( res ) {
                if ( res.success ) {
                    // Remove deleted rows
                    $( '.eiu-article-cb:checked' ).each( function(){
                        $( this ).closest( 'tr' ).fadeOut( 300, function(){ $( this ).remove(); });
                    });
                    $( '#eiu-select-all' ).prop( 'checked', false );
                    $( '#eiu-bulk-msg' ).text( res.data.message || 'Done.' ).css( 'color', '#166534' );
                    setTimeout( updateBulkCount, 400 );
                } else {
                    $( '#eiu-bulk-msg' ).text( ( res.data && res.data.message ) || 'Error.' ).css( 'color', '#dc2626' );
                }
            })
            .fail( function( xhr ){
                var msg = 'Delete failed (HTTP ' + xhr.status + ').';
                try { var r = JSON.parse( xhr.responseText ); if ( r && r.data && r.data.message ) msg = r.data.message; } catch(e){}
                $( '#eiu-bulk-msg' ).text( msg ).css( 'color', '#dc2626' );
            })
            .always( function(){ $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span>Delete Selected' ); });
    });

    // ── Verify reviewer ─────────────────────────────────────────────
    $( document ).on( 'click', '.eiu-btn-verify-reviewer', function() {
        const $btn = $( this );
        const id   = $btn.data( 'id' );
        eiu_ajax( 'eiu_rp_admin_verify_reviewer', { reviewer_id: id }, $btn,
            function() { location.reload(); }
        );
    });

})( jQuery );
