<?php
/**
 * DateCollider Trait
 *
 * @package Plugins/Site/Events/DateCollider
 */
namespace Sugar_Calendar;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class trait used to identify if two DateTimes intersect with two other
 * DateTimes.
 *
 * @since 2.2.0
 */
trait DateCollider {

	/** Boundary **************************************************************/

	/**
	 * Start boundary
	 *
	 * @since 2.2.0
	 * @var DateTime
	 */
	public $boundary_start = null;

	/**
	 * End boundary
	 *
	 * @since 2.2.0
	 * @var DateTime
	 */
	public $boundary_end = null;

	/** Event *****************************************************************/

	/**
	 * Event start
	 *
	 * @since 2.2.0
	 * @var DateTime
	 */
	public $event_start = null;

	/**
	 * Event end
	 *
	 * @since 2.2.0
	 * @var DateTime
	 */
	public $event_end = null;

	/** Sources ***************************************************************/

	/**
	 * Event
	 *
	 * @since 2.2.0
	 * @var Sugar_Calendar\Event
	 */
	public $event = null;

	/** Results ***************************************************************/

	/**
	 * Return value
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	public $intersects = false;

	/**
	 * The name of the method that claims to have intersected
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $intersector = false;

	/** Patterns **************************************************************/

	/**
	 * Array of arrays, keyed by recurring name, with values based on
	 * DateTimeInterface::format().
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $patterns = array(

		// Standard patterns
		'standard' => array(

			// Yearly recurring pattern: Month, Day, and Hour
			'yearly'  => array( 'n', 'j', 'G' ),

			// Monthly recurring pattern: Day and Hour
			'monthly' => array( 'j', 'G' ),

			// Weekly recurring pattern: Day-of-week (0 index) and Hour
			'weekly'  => array( 'N', 'G' ),

			// Daily recurring pattern: Hour
			'daily'   => array( 'G' )
		)
	);

	/**
	 * Array of formats that will trigger a complex match
	 *
	 * Currently multi-Month and multi-Day which is ideal for Calendar
	 * applications, but adjustable for your own needs
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $complex_formats = array( 'n', 'j' );

	/** Matches ***************************************************************/

	/**
	 * Array of matches
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $matches = array();

	/**
	 * Type of pattern to match
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $match_type = '';

	/**
	 * Name of pattern currently being matched
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $match_name = '';

	/**
	 * Pattern currently being matched
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $match_pattern = array();

	/**
	 * Index currently being matched
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $match_index = '';

	/**
	 * Format currently being matched
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $match_format = '';

	/**
	 * Array of values to compare to make matches with
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $match_values = array();

	/** Methods ***************************************************************/

	/**
	 * Perform all checks
	 *
	 * @since 2.2.0
	 *
	 * @param Event    $event
	 * @param DateTime $boundary_start
	 * @param DateTime $boundary_end
	 */
	private function check( $event = false, $boundary_start = false, $boundary_end = false ) {

		// Set object vars
		$this->event          = $event;
		$this->boundary_start = $boundary_start;
		$this->boundary_end   = $boundary_end;

		// Recurrence is over
		if ( $this->recurrence_end_date_passed() ) {
			return;
		}

		// Set Event DateTimes
		$this->set_event_datetimes();

		// Bail if Event starts after boundary ends
		if ( $this->event_start > $this->boundary_end ) {
			return;
		}

		// Loop through patterns
		foreach ( $this->patterns as $type => $types ) {

			// Loop through types
			foreach ( $types as $name => $pattern ) {

				// Skip if not the right kind of recurrence
				if ( $name !== $this->event->recurrence ) {
					continue;
				}

				// Try
				$this->try( $type, $name, $pattern );
			}
		}
	}

	/**
	 * Try to match patterns to boundaries
	 *
	 * @since 2.2.0
	 * @param string $type
	 * @param string $name
	 * @param array  $pattern
	 */
	private function try( $type = '', $name = '', $pattern = array() ) {

		// Set match type, name, and pattern
		$this->match_type    = $type;
		$this->match_name    = $name;
		$this->match_pattern = $pattern;

		// Bail if no pattern
		if ( empty( $this->match_pattern ) ) {
			return;
		}

		// Setup matches
		$this->setup();

		// Bail if no matches
		if ( empty( $this->matches ) ) {
			return;
		}

		// Look for matches
		$this->look();
	}

	/**
	 * Setup all of the possible boundaries to match.
	 *
	 * @since 2.2.0
	 */
	private function setup() {

		// Default return value
		$retval = array();

		// Loop through pattern and try to match them
		foreach ( $this->match_pattern as $index => $format ) {

			// Skip if empty key
			if ( empty( $format ) ) {
				continue;
			}

			// Set match index & key
			$this->match_index  = $index;
			$this->match_format = $format;

			// Prepare values to match
			$this->prepare_values();

			// Initialize
			if ( ! isset( $retval['simple'] ) ) {
				$retval['simple'] = array();
			}

			// Always try the simple match
			$retval['simple'] = $this->match_simple( $retval['simple'] );

			// Complex matching
			if ( $this->do_complex_match() ) {

				// Initialize
				if ( ! isset( $retval['complex'] ) ) {
					$retval['complex'] = array();
				}

				// Complex matches
				$retval['complex'] = $this->match_complex( $retval['complex'] );
			}
		}

		// Set matches
		$this->matches = $retval;
	}

	/**
	 * Prepare values to match
	 *
	 * @since 2.2.0
	 */
	private function prepare_values() {
		$this->match_values = array(

			// Event start
			'es' => $this->event_start->format( $this->match_format ),

			// Event end
			'ee' => $this->event_end->format( $this->match_format ),

			// Boundary start
			'bs' => $this->boundary_start->format( $this->match_format ),

			// Boundary end
			'be' => $this->boundary_end->format( $this->match_format )
		);
	}

	/**
	 * Add simple match conditions
	 *
	 * @since 2.2.0
	 * @param array $match
	 * @return array
	 */
	private function match_simple( $match = array() ) {

		// Event end format is after boundary start
		$match[ "{$this->match_format}_ends" ]   = ( $this->match_values['ee'] >= $this->match_values['bs'] );

		// Event start format is before boundary end
		$match[ "{$this->match_format}_starts" ] = ( $this->match_values['es'] <= $this->match_values['be'] );

		// Return
		return $match;
	}

	/**
	 * Whether or not a complex match should be done
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private function do_complex_match() {

		// Defaults
		$do = array();

		// Multi format, where Event start format is after Event end format
		$do['inverted'] = ( $this->match_values['es'] > $this->match_values['ee'] );

		// Only certain formats are complex
		$do['matches']  = in_array( $this->match_format, $this->complex_formats, true );

		// All conditions must be truthy
		$retval = ! in_array( false, $do, true );

		// Return
		return $retval;
	}

	/**
	 * Add complex match conditions
	 *
	 * @todo multi-day/week/month/year
	 *
	 * @since 2.2.0
	 * @param array $match
	 * @return array
	 */
	private function match_complex( $match = array() ) {

		// Before, Start & End
		if ( ! isset( $match['bse'] ) ) {
			$match['bse'] = array();
		}

		// Before, Event start format less than or equal to Boundary start format
		$match['bse'][ "{$this->match_format}_starts" ] = ( $this->match_values['es'] <= $this->match_values['bs'] );

		// Before, Event end format less than or equal to Boundary end format
		$match['bse'][ "{$this->match_format}_ends" ]   = ( $this->match_values['ee'] <= $this->match_values['be'] );

		// After End & Start
		if ( ! isset( $match['aes'] ) ) {
			$match['aes'] = array();
		}

		// After, Event start format greater than or equal to Boundary end format
		$match['aes'][ "{$this->match_format}_starts" ] = ( $this->match_values['es'] >= $this->match_values['be'] );

		// After, Event end format greater than or equal to Boundary start format
		$match['aes'][ "{$this->match_format}_ends" ]   = ( $this->match_values['ee'] >= $this->match_values['bs'] );

		// Previous, if exists
		$prev_format = isset( $this->match_pattern[ $this->match_index - 1 ] )
			? $this->match_pattern[ $this->match_index - 1 ]
			: '';

		// Previous, Start & End equals
		if ( ! empty( $prev_format ) ) {

			// Before, Event start previous format equals Boundary start previous format
			$match['bse'][ "{$prev_format}_equals" ] = ( $this->event_start->format( $prev_format ) === $this->boundary_start->format( $prev_format ) );

			// After, Event end previous format equals Boundary end previous format
			$match['aes'][ "{$prev_format}_equals" ] = (   $this->event_end->format( $prev_format ) ===   $this->boundary_end->format( $prev_format ) );
		}

		// Return all matches
		return $match;
	}

	/**
	 * Look for matches
	 *
	 * @since 2.2.0
	 */
	private function look() {

		// Loop through possible matches
		foreach ( $this->matches as $key => $to_match ) {

			// Simple match
			if ( 'simple' === $key ) {

				// Accept any simple match
				if ( ! in_array( false, $to_match, true ) ) {
					$this->set_intersect( __METHOD__ );
				}

			// Complex match
			} elseif ( 'complex' === $key ) {

				// Loop through all complex matches
				foreach ( $to_match as $sub_to_match ) {

					// Accept any complex match
					if ( ! in_array( false, $sub_to_match, true ) ) {
						$this->set_intersect( __METHOD__ );
						break;
					}
				}
			}
		}
	}

	/** Recurrence ************************************************************/

	/**
	 * Check if recurrence end date has already passed
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private function recurrence_end_date_passed() {

		// Turn datetimes to timestamps for easier comparisons
		$recur_end  = ! $this->event->is_empty_date( $this->event->recurrence_end )
			? self::get_datetime( $this->event->recurrence_end, $this->event->end_tz )
			: false;

		// Bail if recurring ended after cell start (inclusive of last cell)
		if ( ! empty( $recur_end ) && ( $this->event_start > $recur_end ) ) {
			return true;
		}

		return false;
	}

	/** Abstractions **********************************************************/

	/**
	 * Get a date time object.
	 *
	 * Accepts multiple time zones. The first one is used when the DateTime object
	 * is created, the second one is used to apply an offset relative to the first.
	 *
	 * @since 2.2.0
	 * @param mixed  $timestamp Accepts any string compatible with strtotime()
	 * @param string $timezone1 Default null. Olson time zone ID. Used as base for
	 *                          DateTime object.
	 * @param string $timezone2 Default null. Olson time zone ID. Used to apply
	 *                          offset based on $timezone1.
	 * @return object
	 */
	public static function get_datetime( $timestamp = 0, $timezone1 = null, $timezone2 = null ) {
		return sugar_calendar_get_datetime_object( $timestamp, $timezone1, $timezone2 );
	}

	/**
	 * Is the current time zone preference floating?
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public static function is_timezone_floating() {
		return sugar_calendar_is_timezone_floating();
	}

	/** Setters ***************************************************************/

	/**
	 * Set the Start & End Event DateTime objects
	 *
	 * @since 2.2.0
	 */
	private function set_event_datetimes() {

		// Default to "floating" time zone
		$og_start_tz = $start_tz = $this->boundary_start->getTimezone();
		$og_end_tz   = $end_tz   = $this->boundary_end->getTimezone();

		// All day Events require Event time zones to match boundaries
		if ( ! $this->event->is_all_day() && ! self::is_timezone_floating() ) {

			// Maybe use start time zone
			if ( ! empty( $this->event->start_tz ) ) {
				$start_tz = $this->event->start_tz;
			}

			// Maybe use end time zone
			if ( ! empty( $this->event->end_tz ) ) {
				$end_tz = $this->event->end_tz;
			}
		}

		// Turn datetimes to timestamps for easier comparisons
		$this->event_start = $this->get_datetime( $this->event->start, $start_tz, $og_start_tz );
		$this->event_end   = $this->get_datetime( $this->event->end,   $end_tz,   $og_end_tz   );
	}

	/**
	 * Set the intersect
	 *
	 * @since 2.2.0
	 * @param string $method
	 */
	private function set_intersect( $method = '' ) {
		$this->intersects  = true;
		$this->intersector = $method;
	}
}
