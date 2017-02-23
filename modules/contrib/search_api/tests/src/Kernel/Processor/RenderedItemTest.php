<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Tests the "Rendered item" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\RenderedItem
 */
class RenderedItemTest extends ProcessorTestBase {

  /**
   * List of nodes which are published.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'user',
    'node',
    'search_api',
    'search_api_db',
    'search_api_test',
    'comment',
    'system',
    'filter',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL) {
    parent::setUp('rendered_item');

    // Load additional configuration and needed schemas. (The necessary schemas
    // for using nodes are already installed by the parent method.)
    $this->installConfig(array('system', 'filter', 'node', 'comment', 'user'));
    $this->installSchema('system', array('router'));
    \Drupal::service('router.builder')->rebuild();

    // Creates node types for testing.
    foreach (['article', 'page'] as $type_id) {
      $type = NodeType::create(array(
        'type' => $type_id,
        'name' => $type_id,
      ));
      $type->save();
      node_add_body_field($type);
    }
    CommentType::create([
      'id' => 'comment',
      'label' => 'comment',
      'target_entity_type_id' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'comment',
      'type' => 'comment',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'comment',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Comments',
    ])->save();

    // Insert the anonymous user into the database.
    $anonymous_user = User::create(array(
      'uid' => 0,
      'name' => '',
    ));
    $anonymous_user->save();

    // Default node values for all nodes we create below.
    $node_data = array(
      'status' => NODE_PUBLISHED,
      'type' => 'page',
      'title' => '',
      'body' => array('value' => '', 'summary' => '', 'format' => 'plain_text'),
      'uid' => $anonymous_user->id(),
    );

    // Create some test nodes with valid user on it for rendering a picture.
    $node_data['title'] = 'Title for node 1';
    $node_data['body']['value'] = 'value for node 1';
    $node_data['body']['summary'] = 'summary for node 1';
    $this->nodes[1] = Node::create($node_data);
    $this->nodes[1]->save();
    $node_data['title'] = 'Title for node 2';
    $node_data['body']['value'] = 'value for node 2';
    $node_data['body']['summary'] = 'summary for node 2';
    $this->nodes[2] = Node::create($node_data);
    $this->nodes[2]->save();
    $node_data['type'] = 'article';
    $node_data['title'] = 'Title for node 3';
    $node_data['body']['value'] = 'value for node 3';
    $node_data['body']['summary'] = 'summary for node 3';
    $this->nodes[3] = Node::create($node_data);
    $this->nodes[3]->save();

    // Add a field based on the "rendered_item" property.
    $field_info = array(
      'type' => 'text',
      'property_path' => 'rendered_item',
      'configuration' => array(
        'roles' => array('anonymous'),
        'view_mode' => array(
          'entity:node' => array(
            'page' => 'full',
            'article' => 'teaser',
          ),
          'entity:user' => array(
            'user' => 'compact',
          ),
          'entity:comment' => array(
            'comment' => 'full',
          ),
        ),
      ),
    );
    $field = Utility::createField($this->index, 'rendered_item', $field_info);
    $this->index->addField($field);

    $this->index->setDatasources($this->index->createPlugins('datasource'));

    $this->index->save();


    // Enable the classy and stable themes as the tests rely on markup from
    // that. Set stable as the active theme, but make classy the default. The
    // processor should switch to classy to perform the rendering.
    \Drupal::service('theme_handler')->install(array('classy'));
    \Drupal::service('theme_handler')->install(array('stable'));
    \Drupal::configFactory()->getEditable('system.theme')->set('default', 'classy')->save();
    \Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('stable'));
  }

  /**
   * Tests whether the rendered_item field is correctly filled by the processor.
   */
  public function testAddFieldValues() {
    $items = array();
    foreach ($this->nodes as $node) {
      $items[] = array(
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'node text' . $node->id(),
      );
    }
    $user = User::create([
      'uid' => 2,
      'name' => 'User #2',
    ]);
    $items[] = [
      'datasource' => 'entity:user',
      'item' => $user->getTypedData(),
      'item_id' => $user->id(),
    ];
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => 1,
      'field_name' => 'comment',
      'cid' => 1,
      'comment_type' => 'comment',
      'subject' => 'Subject of comment 1',
    ]);
    $comment->save();
    $items[] = [
      'datasource' => 'entity:comment',
      'item' => $comment->getTypedData(),
      'item_id' => $comment->id(),
    ];
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    foreach ($items as $key => $item) {
      list($datasource_id, $entity_id) = Utility::splitCombinedId($key);
      $type = $this->index->getDatasource($datasource_id)->label();

      $field = $item->getField('rendered_item');
      $this->assertEquals('text', $field->getType(), "$type item $entity_id rendered value is identified as text.");
      /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface[] $values */
      $values = $field->getValues();
      // Test that the value is properly wrapped in a
      // \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
      // object, which contains a string (not, for example, some markup object).
      $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface', $values[0], "$type item $entity_id rendered value is properly wrapped in a text value object.");
      $field_value = $values[0]->getText();
      $this->assertInternalType('string', $field_value, "$type item $entity_id rendered value is a string.");
      $this->assertEquals(1, count($values), "$type item $entity_id rendered value is a single value.");

      switch ($datasource_id) {
        case 'entity:node':
          $this->checkRenderedNode($this->nodes[$entity_id], $field_value);
          break;

        case 'entity:user':
          $this->checkRenderedUser($user, $field_value);
          break;

        case 'entity:comment':
          $this->checkRenderedComment($comment, $field_value);
          break;

        default:
          $this->assertTrue(FALSE);
      }

    }
  }

  /**
   * Verifies that a certain node has been rendered correctly.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedNode(NodeInterface $node, $field_value) {
    // These tests rely on the template not changing. However, if we'd only
    // check whether the field values themselves are included, there could
    // easier be false positives. For example, the title text was present even
    // when the processor was broken, because the schema metadata was also
    // adding it to the output.
    $nid = $node->id();
    $full_view = $node->bundle() === 'page';
    $view_mode = $full_view ? 'full' : 'teaser';
    $this->assertContains("view-mode-$view_mode", $field_value, 'Node item ' . $nid . " rendered in view-mode \"$view_mode\".");
    $this->assertContains('field--name-title', $field_value, 'Node item ' . $nid . ' has a rendered title field.');
    $this->assertContains('>' . $node->label() . '<', $field_value, 'Node item ' . $nid . ' has a rendered title inside HTML-Tags.');
    $this->assertContains('>Member for<', $field_value, 'Node item ' . $nid . ' has rendered member information HTML-Tags.');
    if ($full_view) {
      $body_value = $node->get('body')->getValue()[0]['value'] . '<';
    }
    else {
      $body_value = $node->get('body')->getValue()[0]['summary'] . '<';
    }
    $this->assertContains('>' . $body_value, $field_value, 'Node item ' . $nid . ' has rendered content inside HTML-Tags.');
  }

  /**
   * Verifies that a certain user has been rendered correctly.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedUser(UserInterface $user, $field_value) {
    $this->assertContains('>Member for<', $field_value);
  }

  /**
   * Verifies that a certain comment has been rendered correctly.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedComment(CommentInterface $comment, $field_value) {
    $this->assertContains('>' . $comment->label() . '<', $field_value);
  }

  /**
   * Tests that hiding a rendered item works.
   */
  public function testHideRenderedItem() {
    // Change the processor configuration to make sure that that the rendered
    // item content will be empty.
    $field = $this->index->getField('rendered_item');
    $config = $field->getConfiguration();
    $config['view_mode'] = array(
      'entity:node' => array(
        'page' => '',
        'article' => '',
      ),
    );
    $field->setConfiguration($config);

    // Create items that we can index.
    $items = array();
    foreach ($this->nodes as $node) {
      $items[] = array(
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'text for ' . $node->id(),
      );
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    // Verify that no field values were added.
    foreach ($items as $key => $item) {
      $rendered_item = $item->getField('rendered_item');
      $this->assertFalse($rendered_item->getValues(), 'No rendered_item field value added when disabled for content type.');
    }
  }

  /**
   * Tests whether the property is correctly added by the processor.
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertTrue(array_key_exists('rendered_item', $properties), 'The Properties where modified with the "rendered_item".');
    $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\processor\Property\RenderedItemProperty', $properties['rendered_item'], 'Added property has the correct class.');
    $this->assertTrue(($properties['rendered_item'] instanceof DataDefinitionInterface), 'The "rendered_item" contains a valid DataDefinition instance.');
    $this->assertEquals('field_item:text_long.string', $properties['rendered_item']->getDataType(), 'Correct DataType set in the DataDefinition.');

    // Verify that there are no properties if a datasource is given.
    $properties = $this->processor->getPropertyDefinitions($this->index->getDatasource('entity:node'));
    $this->assertEquals(array(), $properties, '"render_item" property not added when data source is given.');
  }

  /**
   * Tests whether the processor reacts correctly to removed dependencies.
   */
  public function testDependencyRemoval() {
    $expected = array(
      'config' => array(
        'core.entity_view_mode.comment.full',
        'core.entity_view_mode.node.full',
        'core.entity_view_mode.node.teaser',
        'core.entity_view_mode.user.compact',
      ),
    );
    $this->assertEquals($expected, $this->processor->calculateDependencies());

    EntityViewMode::load('node.teaser')->delete();
    $expected = array(
      'entity:node' => array(
        'page' => 'full',
      ),
      'entity:user' => array(
        'user' => 'compact',
      ),
      'entity:comment' => array(
        'comment' => 'full',
      ),
    );
    // We need to reload the index.
    $index = Index::load($this->index->id());
    $field_config = $index->getField('rendered_item')->getConfiguration();
    $this->assertEquals($expected, $field_config['view_mode']);
  }

}
