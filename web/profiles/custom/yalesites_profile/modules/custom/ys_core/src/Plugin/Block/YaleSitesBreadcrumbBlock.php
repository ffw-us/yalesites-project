<?php

namespace Drupal\ys_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\UrlGeneratorInterface;

/**
 * Provides a block to display the breadcrumbs.
 *
 * @Block(
 *   id = "ys_breadcrumb_block",
 *   admin_label = @Translation("YaleSites Breadcrumbs"),
 *   category = @Translation("YaleSites Core"),
 * )
 */
class YaleSitesBreadcrumbBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The breadcrumb manager.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * UrlGenerator.
   *
   * @var \Drupal\Core\Routing\UrlGenerator
   */
  protected $urlGenerator;

  /**
   * Constructs a new YaleSitesBreadcrumbBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_manager
   *   The breadcrumb manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL Generator class.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BreadcrumbBuilderInterface $breadcrumb_manager, RouteMatchInterface $route_match, UrlGeneratorInterface $url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->breadcrumbManager = $breadcrumb_manager;
    $this->routeMatch = $route_match;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('breadcrumb'),
      $container->get('current_route_match'),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $breadcrumbs = $this->removeEmptyLinks($this->getBreadcrumbs());
    kint($breadcrumbs);
    $links = [];
    if (isset($breadcrumbs[1])) {
      //kint($breadcrumbs[1]->getUrl()->toString());
      if ($breadcrumbs[1]->getUrl()->toString() == '/first/news' || $breadcrumbs[1]->getUrl()->toString() == '/first/events') {
        // do nothing...
      }
      else {
        $links = [
          [
            'title' => $this->t('Home'),
            'url' => $this->urlGenerator->generateFromRoute('<front>', []),
            'is_active' => FALSE,
          ],
        ];
      }
    }

    foreach ($breadcrumbs as $breadcrumb) {
      array_push($links, [
        'title' => $breadcrumb->getText(),
        'url' => $breadcrumb->getUrl()->toString(),
        'is_active' => empty($breadcrumb->getUrl()->toString()),
      ]);
    }

    if (isset($breadcrumbs[1])) {
      if ($breadcrumbs[1]->getUrl()->toString() == '/news' || $breadcrumbs[1]->getUrl()->toString() == '/events') {
        array_push($links, [
          'title' => 'at the end',
          'url' => '/test',
          'is_active' => TRUE,
        ]);
      }
    }
    return [
      '#theme' => 'ys_breadcrumb_block',
      '#items' => $links,
    ];
  }

  /**
   * Get a list of breadcrumb links using the Drupal BreadcrumbBuilder.
   *
   * @return \Drupal\Core\Link[]
   *   An array of Drupal links.
   */
  protected function getBreadcrumbs(): array {
    return $this->breadcrumbManager->build($this->routeMatch)->getLinks();
  }

  /**
   * Remove empty links from a list of links.
   *
   * @param \Drupal\Core\Link[] $links
   *   An array of Drupal links.
   *
   * @return \Drupal\Core\Link[]
   *   An array of Drupal links with empty ones removed.
   */
  protected function removeEmptyLinks(array $links): array {
    return array_filter($links, function ($link) {
      return $link->getText() !== '';
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Add cachetag for main menu changes.
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:system.menu.main',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
