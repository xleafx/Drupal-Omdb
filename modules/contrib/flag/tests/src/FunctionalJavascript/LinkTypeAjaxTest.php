<?php

namespace Drupal\Tests\flag\FunctionalJavascript;

use Drupal\flag\Tests\FlagCreateTrait;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Javascript test for ajax links.
 *
 * @group flag
 */
class LinkTypeAjaxTest extends JavascriptTestBase {

  use FlagCreateTrait;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * A user with Flag admin rights.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The node type to use in the test.
   *
   * @var string
   */
  protected $nodeType = 'article';

  /**
   * The flag under test.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The node to be flagged and unflagged.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['flag', 'flag_event_test', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Get the Flag Service.
    $this->flagService = $this->container->get('flag');

    // Create content type.
    $this->drupalCreateContentType(['type' => $this->nodeType]);

    // Create the admin user.
    $this->adminUser = $this->createUser([], NULL, TRUE);

    $this->flag = $this->createFlag('node', [], 'ajax_link');
    $this->node = $this->drupalCreateNode(['type' => $this->nodeType]);
  }

  /**
   * Test the ajax link type.
   */
  public function testAjaxLink() {
    // Create and login as an authenticated user.
    $auth_user = $this->drupalCreateUser([
      'flag ' . $this->flag->id(),
      'unflag ' . $this->flag->id(),
    ]);
    $this->drupalLogin($auth_user);

    // Navigate to the node page.
    $this->drupalGet($this->node->toUrl());

    // Confirm the flag link exists.
    $this->assertSession()->linkExists($this->flag->getFlagShortText());

    // Click the flag link.
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getUnflagShortText());
    $this->assertTrue($this->flagService->getFlagging($this->flag, $this->node, $auth_user));

    // Click the unflag link, repeat the check.
    $this->clickLink($this->flag->getUnflagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getFlagShortText());
    $this->assertFalse($this->flagService->getFlagging($this->flag, $this->node, $auth_user));

    // And flag again.
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getUnflagShortText());
    $this->assertTrue($this->flagService->getFlagging($this->flag, $this->node, $auth_user));

    // Add an unrelated flag, and enable flag events.
    // @see \Drupal\flag_test\EventSubscriber\FlagEvents
    $this->flagService->unflag($this->flag, $this->node, $auth_user);
    $flag_b = $this->createFlag();
    $this->container->get('flag')->flag($flag_b, $this->node, $auth_user);
    $this->container->get('state')
      ->set('flag_test.react_flag_event', $flag_b->id());
    $this->container->get('state')
      ->set('flag_test.react_unflag_event', $flag_b->id());

    // Navigate to the node page.
    $this->drupalGet($this->node->toUrl());

    // Confirm the flag link exists.
    $this->assertSession()->linkExists($this->flag->getFlagShortText());

    // Click the flag link.
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getUnflagShortText());
    $this->assertTrue($this->flagService->getFlagging($this->flag, $this->node, $auth_user));

    // Verifies that the event subscriber was called.
    $this->assertTrue($this->container->get('state')->get('flag_test.is_flagged', FALSE));

    // Click the unflag link, repeat the check.
    $this->clickLink($this->flag->getUnflagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getFlagShortText());
    $this->assertFalse($this->flagService->getFlagging($this->flag, $this->node, $auth_user));

    // Verifies that the event subscriber was called.
    $this->assertTrue($this->container->get('state')->get('flag_test.is_unflagged', FALSE));

    // And flag again.
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl());
    $this->assertSession()->linkExists($this->flag->getUnflagShortText());
    $this->assertTrue($this->flagService->getFlagging($this->flag, $this->node, $auth_user));
  }

}
