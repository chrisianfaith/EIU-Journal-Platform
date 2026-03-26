/**
 * EIU Research Publication – Frontend JS
 * Version: 1.7.0
 *
 * Handles the reviewer dashboard review form (legacy).
 * The submission form and nav user-menu each have their own inline
 * script blocks / dedicated files and do NOT depend on this file.
 */
/* global eiuRP, jQuery */
(function ( $ ) {
    'use strict';

    const { ajaxUrl, nonce, i18n } = eiuRP;

    // ── Utility ─────────────────────────────────────────────────────
    function showMsg( $el, msg, type ) {
        $el.removeClass( 'eiu-rp-success-msg eiu-rp-error-msg' )
           .addClass( type === 'success' ? 'eiu-rp-success-msg' : 'eiu-rp-error-msg' )
           .html( msg )
           .show()
           .get(0).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearMsg( $el ) { $el.hide().text(''); }

    // ── Reviewer Dashboard ───────────────────────────────────────────
    (function initReviewerDashboard() {
        const $wrap = $( '.eiu-rp-dashboard-wrap' );
        if ( ! $wrap.length ) return;

        $wrap.on( 'submit', '.eiu-review-form', function (e) {
            e.preventDefault();

            const $form       = $( this );
            const $btn        = $form.find( '.eiu-btn-review-submit' );
            const formData    = new FormData( this );
            const reviewId    = $form.data( 'review-id' );
            const $successMsg = $( '#eiu-review-success' );
            const $errorMsg   = $( '#eiu-review-error' );

            clearMsg( $successMsg );
            clearMsg( $errorMsg );

            if ( ! formData.get( 'recommendation' ) ) {
                showMsg( $errorMsg, 'Please select a recommendation.', 'error' );
                return;
            }
            if ( ! String( formData.get( 'comments' ) ).trim() ) {
                showMsg( $errorMsg, 'Please provide review comments.', 'error' );
                return;
            }

            $btn.prop( 'disabled', true ).text( i18n.submitting );

            $.post( ajaxUrl, {
                action:         'eiu_rp_submit_review',
                nonce:          nonce,
                review_id:      reviewId,
                recommendation: formData.get( 'recommendation' ),
                comments:       formData.get( 'comments' ),
            })
            .done( function ( res ) {
                if ( res.success ) {
                    showMsg( $successMsg, res.data.message, 'success' );
                    $form.closest( '.eiu-review-form-wrap' ).html(
                        '<div class="eiu-submitted-review"><p class="eiu-submitted-note">' +
                        res.data.message + '</p></div>'
                    );
                    $form.closest( '.eiu-review-card' )
                        .find( '.eiu-rp-badge' )
                        .attr( 'class', 'eiu-rp-badge status-submitted' )
                        .text( 'Submitted' );
                } else {
                    const msg = res.data && res.data.message ? res.data.message : i18n.error;
                    showMsg( $errorMsg, msg, 'error' );
                    $btn.prop( 'disabled', false ).text( 'Submit Review' );
                }
            })
            .fail( function () {
                showMsg( $errorMsg, i18n.error, 'error' );
                $btn.prop( 'disabled', false ).text( 'Submit Review' );
            });
        });
    }());

}( jQuery ));

