<?php
/*
Plugin Name: Add ChurchSuite events shortcode
Plugin URI: https://ChurchWeb.uk
Description: Dev Branch: 11432a.dev : Presents events from a ChurchSuite account using a shortcode in posts or pages.

***** BEGIN CHURCHWEB NOTICE - DO NOT AMEND THIS TEXT AREA  *****
***** Not for Production Use                                *****
***** For ChurchWeb Internal Development Use Only           *****
***** Not for onwards sharing or transmission               *****
***** Dev Branch: 11432a.dev                                *****
***** Removed ChurchWeb Updater, Security and Licensing     *****
***** All Queries to Support@ChurchWeb.uk                   *****
***** Check Dev Branch Before Merging                       *****
***** Not for Production Use                                *****
***** END CHURCHWEB NOTICE - DO NOT AMEND ABOVE TEXT AREA   *****

Tags: churchsuite, events
Version: 0.2.4
Author: ChurchWeb
Author URI: https://ChurchWeb.uk
License: GPL2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Copyright: 2021 ChurchWeb.uk
Text Domain: CWChurchSuiteEventsSC
Domain Path: /languages
Requires at least: 5.7

Originally Forked from https://github.com/whitkirkchurch/include-churchsuite-events
Contributors: jacksonj04
*/

function limit($iterable, $limit)
{
    foreach ($iterable as $key => $value) {
        if (!$limit--) {
            break;
        }
        yield $key => $value;
    }
}

function cs_events_shortcode($atts = [])
{
    if (isset($atts['account'])) {
        $account = $atts['account'];
        $base_url =
            'https://' . $account . '.churchsuite.co.uk/embed/calendar/json';
        unset($atts['account']);
    } else {
        return 'Missing "account" parameter!';
    }

    if (isset($atts['link_titles'])) {
        $link_titles = (bool) $atts['link_titles'];
        unset($atts['link_titles']);
    } else {
        $link_titles = true;
    }

    if (isset($atts['show_years'])) {
        $show_years = $atts['show_years'];
        unset($atts['show_years']);
    } else {
        $show_years = false;
    }

    if (isset($atts['show_date'])) {
        $show_date = $atts['show_date'];
        unset($atts['show_date']);
    } else {
        $show_date = true;
    }

    if (isset($atts['show_end_times'])) {
        $show_end_times = (bool) $atts['show_end_times'];
        unset($atts['show_end_times']);
    } else {
        $show_end_times = false;
    }

    if (isset($atts['show_locations'])) {
        $show_locations = (bool) $atts['show_locations'];
        unset($atts['show_locations']);
    } else {
        $show_locations = true;
    }

    if (isset($atts['show_descriptions'])) {
        $show_descriptions = (bool) $atts['show_descriptions'];
        unset($atts['show_descriptions']);
    } else {
        $show_descriptions = false;
    }

    if (isset($atts['limit_to_count'])) {
        $limit_to_count = (int) $atts['limit_to_count'];
        unset($atts['limit_to_count']);
    } else {
        $limit_to_count = true;
    }

    if (isset($atts['sign_up_events_only'])) {
        $sign_up_events_only = (bool) $atts['sign_up_events_only'];
        unset($atts['sign_up_events_only']);
    } else {
        $sign_up_events_only = false;
    }

    try {
        $params = [];

        foreach ($atts as $attribute => $value) {
            $params[$attribute] = $value;
        }

        $params_string = http_build_query($params);
        $query_url = $base_url . '?' . $params_string;

        $json = file_get_contents($query_url);
        $data = json_decode($json);

        $last_date = null;

        date_default_timezone_set('Europe/London');

        if ($limit_to_count) {
            $data_to_loop = limit($data, $limit_to_count);
        } else {
            $data_to_loop = $data;
        }

        $output .= '<div id="cs_events-container">';

        // This is where most of the magic happens
        foreach ($data_to_loop as $event) {
            // Build the event URL, we use this a couple of times
            $event_url =
                'https://' .
                $account .
                '.churchsuite.co.uk/events/' .
                $event->identifier;

            // Find event color
            $event_color = '#' .
             $event->brand->color;

            // Find event image use brand logo if none set
            if (isset($event->images->lg)){
              $event_image = $event->images->lg->url;   
            } else {
              $event_image = $event->brand->logo;
            }

            // Build the object for the JSON-LD representation
            $json_ld = [
                '@context' => 'http://www.schema.org',
                '@type' => 'Event',
                'name' => $event->name,
                'url' => $event_url,
                'image' => $event_image,
                'description' => $event->description,
                'startDate' => date(
                    DATE_ISO8601,
                    strtotime($event->datetime_start)
                ),
                'endDate' => date(
                    DATE_ISO8601,
                    strtotime($event->datetime_end)
                ),
                'organizer' => [
                    'name' => get_bloginfo( 'name' ),
                    'url' => get_site_url(),
                ],
            ];


            // Set attendance mode
            if ($event->location->type == 'online') {
                $json_ld['eventAttendanceMode'] =
                    'https://schema.org/OnlineEventAttendanceMode';
                $json_ld['location'] = [
                    '@type' => 'VirtualLocation',
                    'url' => $event->location->url,
                ];
            } else {
                $json_ld['eventAttendanceMode'] =
                    'https://schema.org/OfflineEventAttendanceMode';
                $json_ld['location'] = [
                    '@type' => 'Place',
                    'name' => $event->location->name,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'postalCode' => $event->location->address,
                    ],
                ];
            }

            // Flag cancelled events
            if ($event->status == 'cancelled') {
                $json_ld['eventStatus'] = 'https://schema.org/EventCancelled';
            } else {
                $json_ld['eventStatus'] = 'https://schema.org/EventScheduled';
            }

            // And output JSON-LD
            $output .=
                '<script type="application/ld+json">' .
                json_encode($json_ld) .
                '</script>';

            // Turn the time into an actual object
            $start_time = strtotime($event->datetime_start);

            $date = date('Y-m-d', $start_time);

            // Select if Sign up only events
            if ($sign_up_events_only == true && $event->signup_options->signup_enabled == '0') {            
            $output .= '<div style="display:none">';
            }
             else {
                $output .= '<div class="cs_events--event">';  
             }

            // Output the event image
                if ($link_titles == true) {
                    $output .=
                        '<a href="' . $event_url . '">';
                }
                if (isset($event->images->lg)){
                    $output .=
                    '<img class="cs_events--event-image" src="' .
                    $event_image .
                    '" alt="' .  $event->name .'">';  
                    } else {  $output .=
                    '<img class="cs_events--brand-logo" src="' .
                    $event->brand->logo .
                    '" alt="' . $event->brand->name .'">'; 
                 }
                if ($link_titles == true) {
                    $output .= '</a>';
                }
            // Output the event info panel
            $output .= '<div class="cs_events--event-info-panel">';   
            // Output the event title
            if ($link_titles == true) {
                $output .= '<a href="' . $event_url . '">';
            }
            $output .= '<div class="cs_events--event-title" style="background-color:'.
                $event_color . '"><h3 class="cs_events--event-title">';

            if ($event->status == 'cancelled') {
                $output .= '<span style="text-decoration:line-through">';
            } elseif ($event->status == 'pending') {
                $output .= '<span style="font-style: italic">';
            }

            $output .=
                '</span><span class="cs_events--event-name">' .
                $event->name .
                '</span>';

            if ($event->status == 'cancelled') {
                $output .= '</span>';
            } elseif ($event->status == 'pending') {
                $output .= '?</span>';
            }

            $output .= '</h3></div>';

            if ($link_titles == true) {
                $output .= '</a>';
            }

            $output .= '<ul>';

            if ($event->status == 'cancelled') {
                $output .=
                    '<li>This event has been cancelled.</li></ul></div>';
            } else {

             $output .=
                    '<li><span class="cs_events--event-date_icon"></span><span class="cs_events--event-date">' .
                    date('l j F', $start_time);

                 if (
                        $show_years and
                     ($show_years == 'always' or
                            $show_years == 'different' and
                                date('Y', $start_time) != date('Y'))
                   ) {
                       $output .= ' ' . date('Y', $start_time);
                       '</span></li>';
                    }

                $output .=
                   '<li><span class="cs_events--event-time_icon"></span><span class="cs_events--event-time">' .
                    date('g:i a', $start_time);

              if ($show_end_times) {
                  $output .=
                        ' &ndash; ' .
                        date('g.i a', strtotime($event->datetime_end));
              }
              $output .= '</span></li>';

             if ($show_locations && $event->location->name) {
                   $output .=
                       '<li><span class="cs_events--event-loc_icon"></span><span class="cs_events--event-loc">' .
                        htmlspecialchars_decode($event->location->name) .
                      '</span></li>';
             }
             $output .= '</ul>';

               // Output the event sign up button
              if ($event->signup_options->signup_enabled == '1') {
                    $output .= '<a class="cs_events--signup-button__link" href="' . $event_url . '"><div class="cs_events--signup-button" style="background-color:'.
                $event_color . '">Sign Up</div></a>';
              }
            $output .= '</div>'; 

            }
            // Output the event description
            if ($show_descriptions and $event->description != '') {
                $output .= '<div class="cs_events--description">' .
                    htmlspecialchars_decode($event->description) .
                    '</div>';
            }

            $output .= '<div style="clear:both"></div></div>';
        }
        $output .= '</div>';
        return $output;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}


add_shortcode('churchsuite_events', 'cs_events_shortcode');

function CW_load_plugin_css() {
    $plugin_url = plugin_dir_url( __FILE__ );

    wp_enqueue_style( 'events-grid', $plugin_url . 'styles/events-list.css' );
}
add_action( 'wp_enqueue_scripts', 'CW_load_plugin_css' );