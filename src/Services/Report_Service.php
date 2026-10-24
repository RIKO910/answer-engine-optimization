<?php
/**
 * Printable client report (HTML; "Save as PDF" from the browser print dialog).
 *
 * @package AEO
 */

namespace AEO\Services;

use AEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Report.
 */
class Report_Service {

	/**
	 * Register the admin-post handler.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_aeo_report', array( __CLASS__, 'render' ) );
	}

	/**
	 * Report URL (with nonce).
	 *
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				'action'   => 'aeo_report',
				'_wpnonce' => wp_create_nonce( 'aeo_report' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Gather report data.
	 *
	 * @return array<string, mixed>
	 */
	public static function data() {
		$analytics  = ( new Analytics_Service() )->get_overview();
		$audit      = ( new Audit_Service() )->get_results();
		$visibility = ( new Ai_Visibility() )->scan_summary();
		$traffic    = Traffic_Log::report( 30 );
		$gsc        = get_transient( 'aeo_gsc_report' );
		$bing       = Bing_Import::report();

		return compact( 'analytics', 'audit', 'visibility', 'traffic', 'gsc', 'bing' );
	}

	/**
	 * Output the report and exit.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'aeo_view_reports' ) || ! check_admin_referer( 'aeo_report' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'answer-engine-optimization' ), 403 );
		}

		$d      = self::data();
		$agency = (array) Settings::get( 'agency', array() );
		$brand  = ! empty( $agency['white_label_name'] ) ? $agency['white_label_name'] : __( 'Answer Engine Optimization', 'answer-engine-optimization' );
		$logo   = $agency['white_label_logo'] ?? '';
		$site   = get_bloginfo( 'name' );
		$a      = $d['analytics'];
		$v      = $d['visibility'];
		$t      = $d['traffic'];
		$sum    = (array) ( $d['audit']['summary'] ?? array() );

		$bots_total = array_sum( array_column( (array) $t['bots'], 'hits' ) );
		$refs_total = array_sum( array_column( (array) $t['referrals'], 'visits' ) );

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( sprintf( '%s — AI visibility report', $site ) ); ?></title>
<style>
	:root { --fg:#18181b; --muted:#71717a; --line:#e4e4e7; --accent:#4f46e5; }
	* { box-sizing: border-box; }
	body { margin: 0; font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--fg); background: #fafafa; }
	.page { max-width: 880px; margin: 32px auto; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 40px; }
	header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 20px; margin-bottom: 28px; }
	header img { max-height: 36px; }
	h1 { font-size: 22px; margin: 0; letter-spacing: -0.01em; }
	h2 { font-size: 15px; margin: 32px 0 12px; }
	.muted { color: var(--muted); }
	.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
	.kpi { border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
	.kpi b { display: block; font-size: 22px; font-weight: 600; margin-top: 4px; }
	table { width: 100%; border-collapse: collapse; font-size: 13px; }
	th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
	th { color: var(--muted); font-weight: 500; }
	.bar { height: 6px; background: #f4f4f5; border-radius: 3px; overflow: hidden; }
	.bar span { display: block; height: 100%; background: var(--accent); }
	.print { position: fixed; right: 20px; top: 20px; background: var(--accent); color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font: inherit; cursor: pointer; }
	@media print { body { background: #fff; } .page { border: 0; margin: 0; padding: 0; } .print { display: none; } }
	@media (max-width: 640px) { .kpis { grid-template-columns: repeat(2, 1fr); } .page { padding: 20px; margin: 0; border-radius: 0; } }
</style>
</head>
<body>
<button class="print" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'answer-engine-optimization' ); ?></button>
<div class="page">
	<header>
		<div>
			<h1><?php echo esc_html( $site ); ?></h1>
			<div class="muted"><?php echo esc_html( sprintf( 'AI visibility report · %s', wp_date( get_option( 'date_format' ) ) ) ); ?></div>
		</div>
		<div><?php echo $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $brand ) . '">' : '<strong>' . esc_html( $brand ) . '</strong>'; ?></div>
	</header>

	<div class="kpis">
		<div class="kpi"><span class="muted">AEO score</span><b><?php echo (int) ( $a['aeo_score']['average'] ?? 0 ); ?></b></div>
		<div class="kpi"><span class="muted">AI citations</span><b><?php echo $v ? esc_html( ( (int) $v['cited'] ) . ' / ' . ( (int) $v['checked'] ) ) : '—'; ?></b></div>
		<div class="kpi"><span class="muted">AI crawler visits (30d)</span><b><?php echo (int) $bots_total; ?></b></div>
		<div class="kpi"><span class="muted">Visits from AI (30d)</span><b><?php echo (int) $refs_total; ?></b></div>
	</div>

	<?php if ( $v && ! empty( $v['engines'] ) ) : ?>
	<h2>AI answers citing the site</h2>
	<p class="muted"><?php echo esc_html( sprintf( 'Latest check: %s. Each tracked question was asked to each assistant with web search on.', $v['scanned_at'] ) ); ?></p>
	<table>
		<tr><th>Assistant</th><th>Questions checked</th><th>Cited</th><th>Brand mentioned</th><th>Avg. source position</th></tr>
		<?php foreach ( $v['engines'] as $e ) : ?>
		<tr><td><?php echo esc_html( $e['label'] ); ?></td><td><?php echo (int) $e['checked']; ?></td><td><?php echo (int) $e['cited']; ?></td><td><?php echo (int) $e['mentioned']; ?></td><td><?php echo null === $e['avg_position'] ? '—' : esc_html( (string) $e['avg_position'] ); ?></td></tr>
		<?php endforeach; ?>
	</table>
		<?php if ( count( $v['share_of_voice'] ) > 1 ) : ?>
	<h2>Share of voice (citations vs competitors)</h2>
	<table>
			<?php foreach ( $v['share_of_voice'] as $s ) : ?>
		<tr><td style="width:40%"><?php echo esc_html( $s['name'] ); ?></td><td><div class="bar"><span style="width:<?php echo (int) $s['percent']; ?>%"></span></div></td><td style="width:60px"><?php echo (int) $s['percent']; ?>%</td></tr>
			<?php endforeach; ?>
	</table>
		<?php endif; ?>
	<?php endif; ?>

	<h2>Content readiness</h2>
	<table>
		<tr><th>Metric</th><th>Value</th></tr>
		<tr><td>Pages with complete schema</td><td><?php echo esc_html( ( (int) $a['schema_coverage']['with_schema'] ) . ' of ' . ( (int) $a['schema_coverage']['total'] ) . ' (' . (int) $a['schema_coverage']['percent'] . '%)' ); ?></td></tr>
		<tr><td>Pages with FAQs</td><td><?php echo esc_html( ( (int) $a['faq_coverage']['with_faq'] ) . ' (' . (int) $a['faq_coverage']['percent'] . '%)' ); ?></td></tr>
		<tr><td>Open audit issues</td><td><?php echo esc_html( sprintf( '%d (%d critical, %d warnings, %d opportunities)', (int) ( $sum['total'] ?? 0 ), (int) ( $sum['critical'] ?? 0 ), (int) ( $sum['warning'] ?? 0 ), (int) ( $sum['opportunity'] ?? 0 ) ) ); ?></td></tr>
	</table>

	<?php if ( ! empty( $t['bots'] ) ) : ?>
	<h2>AI crawlers (last 30 days)</h2>
	<table>
		<tr><th>Crawler</th><th>Company</th><th>Requests</th></tr>
		<?php foreach ( array_slice( $t['bots'], 0, 10 ) as $b ) : ?>
		<tr><td><?php echo esc_html( \AEO\Frontend\Ai_Crawlers::BOTS[ $b['agent'] ]['name'] ?? $b['agent'] ); ?></td><td><?php echo esc_html( $b['source'] ); ?></td><td><?php echo (int) $b['hits']; ?></td></tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>

	<?php if ( ! empty( $t['referrals'] ) ) : ?>
	<h2>Visitors from AI assistants (last 30 days)</h2>
	<table>
		<tr><th>Assistant</th><th>Visits</th></tr>
		<?php foreach ( $t['referrals'] as $r ) : ?>
		<tr><td><?php echo esc_html( $r['source'] ); ?></td><td><?php echo (int) $r['visits']; ?></td></tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>

	<?php if ( ! empty( $d['bing']['total'] ) ) : ?>
	<h2>Bing Copilot citations (imported)</h2>
	<p class="muted"><?php echo esc_html( sprintf( '%d citations in the imported Bing Webmaster Tools data.', (int) $d['bing']['total'] ) ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $a['top_pages'] ) ) : ?>
	<h2>Best-prepared pages</h2>
	<table>
		<tr><th>Page</th><th>AEO score</th></tr>
		<?php foreach ( array_slice( $a['top_pages'], 0, 8 ) as $p ) : ?>
		<tr><td><?php echo esc_html( $p['title'] ); ?></td><td><?php echo (int) $p['score']; ?></td></tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</div>
</body>
</html>
		<?php
		exit;
	}
}
