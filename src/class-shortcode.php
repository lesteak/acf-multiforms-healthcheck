<?php

namespace acf_multiforms_healthcheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use acf_multiforms_healthcheck\Utils;

/**
 * Output our [acf_multiforms_healthcheck] shortcode and process the ACF front-end form.
 */
class Shortcode {
	/**
	 * Our form ID, used internally to identify this specific ACF front-end form.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The post type this form should create.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Group field keys to be used
	 *
	 * @var array
	 */
	private $group_keys;

	/**
	 * The constructor saves the necessary properties that we just described above.
	 */
	public function __construct() {
		//field mapping for languages
		$this->id          = 'acf-multiforms-healthcheck';
		$this->post_type   = 'healthchecks';
		$this->group_keys 	= '';
		$this->hooks();
	}

	/**
	 * Register our hooks
	 *
	 * @return void
	 */
	public function hooks() {
		/**
		 * Register our [acf_multiforms_healthcheck] shortcode.
		 */
		add_shortcode( 'acf_multiforms_healthcheck', [ $this, 'output_shortcode' ] );

		/**
		 * Process the ACF form submission.
		 */
		add_action( 'acf/save_post', [ $this, 'process_acf_form' ], 20 );
	}

	/**
	 * Output the shortcode content: if form is not finished, output the form.
	 * If user just filled the last form step, output a thanks message.
	 *
	 * @return string The content of our shortcode.
	 */
	public function output_shortcode() {
		ob_start();

		if ( ! function_exists( 'acf_form' ) ) {
			return;
		}

		$this->group_keys = $this->get_group_keys();

		// User is currently filling the form, we display it.
		if ( ! $this->current_multiform_is_finished() ) {
			$this->output_acf_form( [
				'post_type' => $this->post_type,
			] );

		// Form has been filled entirely, we display a thanks message.
		} else {
			_e( 'Thanks for your submission, we will get back to you very soon!' );
		}

		return ob_get_clean();
	}

	/**
	 * Output the ACF front end form.
	 * Don't forget to add `acf_form_head()` in the header of your theme.
	 * 
	 * @link https://www.advancedcustomfields.com/resources/acf_form/
	 * @param array $args
	 * @return void
	 */
	private function get_group_keys() {		
		$fields = acf_get_fields(2068);
		foreach ($fields as $field) {
			$group_keys[] = $field['key'];
		}

		return $group_keys;
	}

	/**
	 * Output the ACF front end form.
	 * Don't forget to add `acf_form_head()` in the header of your theme.
	 * 
	 * @link https://www.advancedcustomfields.com/resources/acf_form/
	 * @param array $args
	 * @return void
	 */
	private function output_acf_form( $args = [] ) {
		// Get post_id from URL (if we are @ step 2 and above), or create a new_post (if we are @ step 1).
		$requested_post_id = $this->get_request_post_id();
		// Get the current step we are at in the form.
		$requested_step    = $this->get_request_step();
		
		$args = wp_parse_args(
			$args,
			[
				'post_id'     => $requested_post_id,
				'step'        => 'new_post' === $requested_post_id ? 1 : $requested_step,
				'total_steps' => 	count($this->group_keys),
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		$submit_label           = $args['step'] < count( $this->group_keys ) ? __( 'Next step' ) : __( 'Finish' );
		$current_step_group = ( $args['post_id'] !== 'new_post' && $args['step'] > 1 ) ? $this->group_keys[ (int) $args['step'] - 1 ] : $this->group_keys[0];

		// Optional: display a custom message before the form.
		$this->display_custom_message_before_form( $args );

		/**
		 * Display the form with acf_form().
		 *
		 * The key here is to tell ACF which fields groups (metaboxes) we want to display,
		 * depending on the current form step we are at.
		 * This is done via the "fields" parameter below.
		 */
		acf_form(
			[
				'id' 				=> $this->id,
				'post_id'			=> $args['post_id'],
				'new_post'			=> [
					'post_type'		=> $args['post_type'],
					'post_status'	=> $args['post_status'],
				],
				'fields'      => array($current_step_group),
				'submit_value'      => $submit_label,
				'html_after_fields' => $this->output_hidden_fields( $args ),
			]
		);
	}

	/**
	 * Display a custom message before the form
	 *
	 * @param array $args The form arguments passed to acf_form().
	 * @return void
	 */
	private function display_custom_message_before_form( $args ) {
		// if ( $args['post_id'] === 'new_post' ) {
		// 	// $message = __( 'Welcome to this form! This custom message should be different depending on the current step you are at.' );
		// } else {
		// 	switch ( $args['step'] ) {
		// 		case 2:
		// 		default:
		// 			// $message = sprintf( __( 'Hi %1$s, thanks for your interest! Please give us some more details :)' ), get_field( 'full_name', (int) $args['post_id'] ) );
		// 			break;

		// 		case 3:
		// 			// $message = sprintf( __( 'Thanks %1$s! That is the last step.' ), get_field( 'full_name', (int) $args['post_id'] ) );
		// 			break;
		// 	}
		// }
		// if ( $message ) {
		// 	printf( '<p class="steps">%1$s</p>', $message );
		// }

		$message = 'Step&nbsp;<span>' . $args['step'] . '</span>&nbsp;of&nbsp;<span>' . $args['total_steps'] . '</span>';
		$step_percentage = $args['step'] / $args['total_steps'] * 100;
		

		echo '<div class="steps">' . $message . '</div>';
		echo '<div class="step-bar-container"><div class="step-bar" style="max-width: ' . $step_percentage . '%; flex: 0 0 ' . $step_percentage . '%; -ms-flex: 0 0 ' . $step_percentage . '%"></div></div>';
	}

	/**
	 * Output some vital hidden fields in our form in order to properly process it
	 * and redirect user to next step accordingly.
	 * Basically, we need to pass this form ID (to be able to do stuff when it's submitted,
	 * and do nothing when other ACF forms are submitted).
	 * We also need to pass the current step we are at; we could get it from $_GET but
	 * I find it better to have a single source of truth to pick data from, instead of having to mix
	 * between $_POST and $_GET when processing the form.
	 *
	 * @param array $args The form arguments passed to acf_form().
	 * @return string HTML hidden <input /> fields.
	 */
	private function output_hidden_fields( $args ) {
		$inputs   = [];
		$inputs[] = sprintf( '<input type="hidden" name="ame-multiform-id" value="%1$s"/>', $this->id );
		$inputs[] = isset( $args['step'] ) ? sprintf( '<input type="hidden" name="ame-current-step" value="%1$d"/>', $args['step'] ) : '';

		return implode( ' ', $inputs );
	}

	/**
	 * Helper function to check if the current $_GET['post_id] is a valid post for this form.
	 *
	 * @return int|boolean Returns the post ID if post is considered valid, or "new_post" to initiate a blank "new post" form.
	 */
	private function get_request_post_id() {
		if ( isset( $_GET['post_id'] ) && $this->requested_post_is_valid() && $this->can_continue_current_multiform() ) {
			return (int) $_GET['post_id'];
		}

		return 'new_post';
	}

	/**
	 * Analyse the WP_Post related to the $_GET['post_id'] we received, and determine if
	 * this specific post should be used for this ACF form request.
	 *
	 * @return boolean Whether the requested post can be edited.
	 */
	private function requested_post_is_valid() {
		return ( get_post_type( (int) $_GET['post_id'] ) === $this->post_type && get_post_status( (int) $_GET['post_id'] ) === 'publish' );
	}

	/**
	 * Can we continue current post/form edition?
	 * I added this method to offer a granular way to authorize the current multi-steps form edition.
	 * In our case, we analyze a token passed in URL to determine if it matches a post meta, so that continuing
	 * the form edition can not be done by anyone passing a random $_GET['post_id] parameter without its correct secret token.
	 * Any logged-in user verification could be done here.
	 * 
	 * @return boolean If the current multiform edition should continue, or should we discard it and initiate a "new post" form.
	 */
	private function can_continue_current_multiform() {
		if ( ! isset( $_GET['token'] ) ) {
			return false;
		}

		$token_from_url       = sanitize_text_field( $_GET['token'] );
		$token_from_post_meta = get_post_meta( (int) $_GET['post_id'], 'secret_token', true );

		return ( $token_from_url === $token_from_post_meta );
	}

	/**
	 * Get the requested form step. Used to display the proper metaboxes.
	 *
	 * @return int Current step, fallback to 1 (first set of metaboxes).
	 */
	private function get_request_step() {
		if ( isset( $_POST['ame-current-step'] ) && absint( $_POST['ame-current-step'] ) <= count( $this->group_keys ) ) {
			return absint( $_POST['ame-current-step'] );
		}

		else if ( isset( $_GET['step'] ) && absint( $_GET['step'] ) <= count( $this->group_keys ) ) {
			return absint( $_GET['step'] );
		}

		return 1;
	}

	/**
	 * Process the form!
	 * ACF did its magic and created/updated the post with proper meta values.
	 * Now let's add some custom logic to update the title and redirect user to next form step,
	 * or final "thank you" finished state of the form.
	 *
	 * @param int $post_id ACF will give us the post ID.
	 * @return void
	 */
	public function process_acf_form( $post_id ) {
		// Bail early if we are editing a post in back-office, or if we're dealing with a different front-end ACF form.
		if ( is_admin() || ! isset( $_POST['ame-multiform-id'] ) || $_POST['ame-multiform-id'] !== $this->id ) {
			return;
		}

		$this->group_keys = $this->get_group_keys();

		$current_step = $this->get_request_step();

		// First step: ACF just created the post, we might want to store some initial values.
		if ( $current_step === 1 ) {
			// Post title should be empty, we update it to a more readable one.
			$updated_post = wp_update_post( 
				[
					'ID'    => (int) $post_id,
					'post_title' => get_query_var('initiative_id'),
				],
				true
			);

			// Generate a secret token that will be required in URL to continue this form flow and edit this specific WP_Post.
			$token = wp_generate_password( rand( 10, 20 ), false, false );
			update_post_meta( (int) $post_id, 'secret_token', $token );

		}

		// First and middle steps: we are "editing" the post but user has not yet finished the entire flow.
		if ( $current_step < count( $this->group_keys ) ) {
			// Add the post ID in URL and inform our front-end logic that we want to display the NEXT step.
			$query_args = [
				'step'    => ++$current_step,
				'post_id' => $post_id,
				'token'   => isset( $token ) ? $token : $_GET['token'],
			];

			update_post_meta($post_id, 'incomplete', 1);
			$redirect_url = add_query_arg( $query_args, wp_get_referer() );

		// Final step: maybe add an admin email to a queue, change post_status... Anything, really!
		} else {
			// Pass a "finished" parameter to inform our front-end logic that we're done with the form.
			$query_args = [ 'finished' => 1 ];
			delete_post_meta( $post_id, 'incomplete');

			//ensure that hc data is stored in initiative
			update_post_meta( get_query_var('initiative_id'), 'recent_hc', $post_id);
			update_post_meta( get_query_var('initiative_id'), 'last_hc_date', get_the_date('Y-m-d H:i:s', $post_id) );
			
			$redirect_url = add_query_arg('updated', 'healthcheck', get_permalink(get_query_var('initiative_id')));
		}

		// Redirect user back to the form page, with proper new $_GET parameters.
		wp_safe_redirect( $redirect_url );

		exit();
	}

	/**
	 * Determine if the current multiform flow is over.
	 *
	 * @return boolean Whether the current multiform flow is over.
	 */
	private function current_multiform_is_finished() {
		return ( isset( $_GET['finished'] ) && 1 === (int) $_GET['finished'] );
	}
}
