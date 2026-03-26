<?php
/**
 * Admin Welcome / Disclaimer Modal.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="eiu-rp-welcome-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:99999;display:flex;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:10px;max-width:560px;width:92%;padding:44px 40px;box-shadow:0 12px 48px rgba(0,0,0,.28);font-family:Arial,Helvetica,sans-serif;">

    <div style="text-align:center;margin-bottom:28px;">
      <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;background:#003087;border-radius:50%;margin-bottom:16px;">
        <span class="dashicons dashicons-welcome-learn-more" style="color:#fff;font-size:36px;width:36px;height:36px;line-height:1;"></span>
      </div>
      <h2 style="color:#003087;margin:0;font-size:22px;line-height:1.3;">
        <?php esc_html_e( 'Welcome to EIU JOURNAL SYSTEM', 'eiu-rp' ); ?>
      </h2>
    </div>

    <div style="background:#f0f4ff;border:1px solid #c7d5f5;border-left:5px solid #003087;padding:20px 24px;border-radius:6px;margin-bottom:28px;color:#222;line-height:1.7;font-size:14px;">
      <p style="margin:0 0 12px;font-weight:bold;color:#003087;font-size:15px;">
        <?php esc_html_e( 'Monitoring Acknowledgement', 'eiu-rp' ); ?>
      </p>
      <p style="margin:0 0 10px;">
        <?php esc_html_e( 'All activities are monitored by the EIU IT Department.', 'eiu-rp' ); ?>
      </p>
      <p style="margin:0;">
        <?php esc_html_e( 'Please click "Accept" to confirm that you understand that the EIU IT Department has full access to activity logs and user actions.', 'eiu-rp' ); ?>
      </p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field( 'eiu_rp_welcome' ); ?>
      <input type="hidden" name="eiu_rp_welcome_accept" value="1">
      <div style="text-align:center;">
        <button type="submit"
          style="background:#003087;color:#fff;border:none;padding:14px 48px;border-radius:7px;font-size:16px;font-weight:bold;cursor:pointer;letter-spacing:.3px;box-shadow:0 4px 12px rgba(0,48,135,.25);">
          <?php esc_html_e( 'Accept', 'eiu-rp' ); ?>
        </button>
      </div>
    </form>

    <p style="text-align:center;color:#999;font-size:11px;margin:20px 0 0;">
      EIU IT Department &mdash;
      <a href="https://eiu.ac" target="_blank" rel="noopener noreferrer" style="color:#003087;">eiu.ac</a>
      &bull; support@eiu.ac
    </p>

  </div>
</div>
