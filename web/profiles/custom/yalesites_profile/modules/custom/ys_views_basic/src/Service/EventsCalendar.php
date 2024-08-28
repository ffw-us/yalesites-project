<?php

namespace Drupal\ys_views_basic\Service;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\smart_date_recur\Entity\SmartDateRule;

/**
 * Provides an Event Calendar service for generating calendar views.
 */
class EventsCalendar implements EventsCalendarInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Constructs a EventsCalendar object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    AliasManagerInterface $alias_manager,
  ) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareCalendarView(string $month, string $year): array {
    // Create a date object for the first day of the given month and year.
    $firstDayOfMonth = new DrupalDateTime("$year-$month-01");
    $totalDaysInMonth = (int) $firstDayOfMonth->format('t');
    $startDayOfWeek = (int) $firstDayOfMonth->format('w');
    $lastDayOfMonth = new DrupalDateTime("$year-$month-$totalDaysInMonth");
    $endDayOfWeek = (int) $lastDayOfMonth->format('w');

    $paddingStart = $startDayOfWeek;
    $paddingEnd = 6 - $endDayOfWeek;
    $totalCells = $totalDaysInMonth + $paddingStart + $paddingEnd;
    $totalRows = (int) ceil($totalCells / 7);
    $calendarRows = [];

    // Calculate the previous month and year.
    $previousMonthDate = clone $firstDayOfMonth;
    $previousMonthDate->modify('-1 month');
    $daysInPreviousMonth = (int) $previousMonthDate->format('t');
    $previousMonth = $previousMonthDate->format('m');
    $previousYear = $previousMonthDate->format('Y');

    // Calculate the next month and year.
    $nextMonthDate = clone $lastDayOfMonth;
    $nextMonthDate->modify('+1 month');
    $nextMonth = $nextMonthDate->format('m');
    $nextYear = $nextMonthDate->format('Y');

    $currentDay = 1;

    for ($row = 0; $row < $totalRows; $row++) {
      $calendarRows[$row] = [];
      for ($cell = 0; $cell < 7; $cell++) {
        if ($row == 0 && $cell < $paddingStart) {
          // Fill in days from the previous month.
          $day = $daysInPreviousMonth - ($paddingStart - $cell - 1);
          $calendarRows[$row][] = $this->createCalendarCell($day, $previousMonth, $previousYear);
        }
        elseif ($row == $totalRows - 1 && $cell > $endDayOfWeek) {
          // Fill in days from the next month.
          $day = $cell - $endDayOfWeek;
          $calendarRows[$row][] = $this->createCalendarCell($day, $nextMonth, $nextYear);
        }
        else {
          // Normal date cell within the current month.
          $calendarRows[$row][] = $this->createCalendarCell($currentDay, $month, $year);
          $currentDay++;
        }
      }
    }

    return $calendarRows;
  }

  /**
   * {@inheritdoc}
   */
  public function createCalendarCell(int $day, string $month, string $year): array {
    return [
      'date' => [
        'day' => str_pad($day, 2, '0', STR_PAD_LEFT),
        'month' => $month,
        'year' => $year,
      ],
      'events' => $this->getEvents($day, $month, $year),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(int $day, string $month, string $year): array {
    // Create DrupalDateTime objects for the start and end of the day.
    $startDate = new DrupalDateTime("$year-$month-$day 00:00:00");
    $endDate = new DrupalDateTime("$year-$month-$day 23:59:59");

    $startTimestamp = $startDate->getTimestamp();
    $endTimestamp = $endDate->getTimestamp();

    // Query to fetch event nodes that overlap with the current day.
    $query = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition('status', 1)
      ->condition('field_event_date.value', $endTimestamp, '<=')
      ->condition('field_event_date.end_value', $startTimestamp, '>=')
      ->sort('field_event_date.value');

    $nids = $query->execute();
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->nodeStorage->loadMultiple($nids);
    if ($day == 8) {
      $a=1;
    }
    $events = [];
    foreach ($nodes as $node) {
      if (!$node->get('field_event_date')->isEmpty()) {
        $eventDates = $node->get('field_event_date')->getValue();

        // Handle recurrence rules if present.
        if ($node->field_event_date?->rrule) {

          /** @var \Drupal\smart_date_recur\Entity\SmartDateRule $rule */
          $rule = $this->entityTypeManager->getStorage('smart_date_rule')
            ->load($node->field_event_date->rrule);

          if ($rule instanceof SmartDateRule) {
            // Iterate over the stored instances to find occurrences for the
            // current day.
            foreach ($rule->getStoredInstances() as $instance) {
              $instanceStartTimestamp = $instance['value'];
              $instanceEndTimestamp = $instance['end_value'];

              // Check if the instance overlaps with the current day.
              if ($instanceStartTimestamp <= $endTimestamp && $instanceEndTimestamp >= $startTimestamp) {
                $time = $this->isAllDay($instanceStartTimestamp, $instanceEndTimestamp)
                  ? 'All Day'
                  : date('g:iA', $instanceStartTimestamp) . ' to ' . date('g:iA', $instanceEndTimestamp);

                $events[] = $this->createEventArray($node, $time, $instanceStartTimestamp);
              }
            }
          }
        }
        else {
          // Iterate through the nodes to extract event details.
          foreach ($eventDates as $eventDate) {
            $eventStartTimestamp = $eventDate['value'];
            $eventEndTimestamp = $eventDate['end_value'];

            // Check if the event overlaps with the current day.
            if ($eventStartTimestamp <= $endTimestamp && $eventEndTimestamp >= $startTimestamp) {
              $time = $this->isAllDay($eventStartTimestamp, $eventEndTimestamp)
                ? 'All Day'
                : date('g:iA', $eventStartTimestamp) . ' to ' . date('g:iA', $eventEndTimestamp);

              // Add event to the list if it overlaps with the current day.
              $events[] = $this->createEventArray($node, $time, $eventStartTimestamp);
            }
          }
        }
      }
    }

    // Sort events by the start timestamp.
    usort($events, function ($a, $b) {
      return $a['timestamp'] <=> $b['timestamp'];
    });

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function createEventArray($node, string $time, int $timestamp): array {
    // Extract categories from the event node.
    $categories = implode(', ', array_map(function ($term) {
      return $term->label();
    }, $node->get('field_category')->referencedEntities()));

    // Extract event types from the event node.
    $eventTypes = implode(', ', array_map(function ($term) {
      return $term->label();
    }, $node->get('field_localist_event_type')->referencedEntities()));

    // Build and return the event array.
    return [
      'category' => $categories,
      'title' => $node->label(),
      'url' => $this->aliasManager->getAliasByPath('/node/' . $node->id()),
      'time' => $time,
      'type' => $eventTypes,
      'timestamp' => $timestamp,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isAllDay(int $start_ts, int $end_ts, string $timezone = NULL): bool {
    if ($timezone) {
      $default_tz = date_default_timezone_get();
      date_default_timezone_set($timezone);
    }

    $temp_start = date('H:i', $start_ts);
    $temp_end = date('H:i', $end_ts);

    if ($timezone) {
      date_default_timezone_set($default_tz);
    }

    return $temp_start == '00:00' && $temp_end == '23:59';
  }

}
