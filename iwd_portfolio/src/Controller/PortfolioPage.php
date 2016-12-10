<?php

namespace Drupal\iwd_portfolio\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create the paged Portfolio page.
 *
 */
class PortfolioPage extends ControllerBase {

  /**
   * Entity storage for node entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * PagerExamplePage constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   Entity storage for node entities.
   */
  public function __construct(EntityStorageInterface $node_storage) {
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * Content callback for the pager_example.page route.
   */
  public function buildPortfolioPage() {
  // I feel like I'm hacking some of this together. I've placed comments
  // in a couple of specific places below.

    // See if there are any published Portfolio pieces of content.
    $query = $this->nodeStorage->getQuery()
      ->condition('status', 1)
      ->condition('type', 'portfolio')
      ->count();
    $count_nodes = $query->execute();

    if ($count_nodes == 0) {
      $build['no-nodes'] = [
        '#markup' => $this->t('Whoops! How embarrassing. There are no items for the portfolio.'),
      ];
      return $build;
    }

    // Prepare a pager for the portfolio items
    $query = $this->nodeStorage->getQuery()
      ->condition('status', 1)
      ->condition('type', 'portfolio')
      ->sort('field_sort_order')
      ->pager(9);
    $entity_ids = $query->execute();

    $nodes = $this->nodeStorage->loadMultiple($entity_ids);

    // Set up the items for the unordered list
    $items = [];
    foreach ($nodes as $node) {
      if (isset($node->field_image[0])) {
        // @todo: Check if this is the right way to be doing this.
        //        I feel like I'm really abusing things to get my image.
        $image_data = $node->field_image[0]->getValue();
        $file = File::load($image_data['target_id']);
        $items[] = array(
          'link_text' => array(
            '#theme' => 'image_style',
            '#uri' => $file->getFileUri(),
            '#style_name' => 'portfolio_thumbnail',
          ),
          'nid' => $node->nid->value,
        );
      }
      else {
        $items[] = array(
          'link_text' => array(
            '#type' => 'markup',
            '#markup' => $node->getTitle(),
          ),
          'nid' => $node->nid->value,
        );
      }
    }

    $list = array();
    foreach ($items as $item) {
      $url = Url::fromUserInput('/node/' . $item['nid']);
      $options = array(
        'attributes' => array(
          'class' => array(
            'use-ajax',
          ),
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
          ]),
        ),
      );
      $url->setOptions($options);
      // @todo: Check if this is the right way to be doing this.
      //        I'm not in love with the drupal_render and I may be abusing
      //        Link as well.
      $list[] = Link::fromTextAndUrl(drupal_render($item['link_text']), $url)->toString();
    }

    // Add disclaimer text
    $build['disclaimer'] = array(
      '#type' => 'item',
      '#markup' => $this->t('Here is a sampling of some projects on which I have worked and that I am allowed to display on my website. There are many more projects that I may not share because I was subcontracting for another agency.'),
    );

    // Let them know they can get more information
    $build['instructions'] = array(
      '#type' => 'item',
      '#markup' => $this->t('You may click on an image to get more details.'),
    );

    // Build a render array which will be themed as
    // an unordered list with a pager.
    $build['page'] = array(
      '#items' => $list,
      '#theme' => 'item_list',
    );
    $build['pager'] = array(
      '#type' => 'pager',
      '#weight' => 10,
    );
    $build['#cache']['tags'][] = 'node_list';
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $build;
  }

}
