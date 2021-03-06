<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Target;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\ReferenceNotFoundException;
use Drupal\feeds\Feeds\Target\EntityReference;
use Drupal\feeds\FieldTargetDefinition;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\EntityReference
 * @group feeds
 */
class EntityReferenceTest extends FieldTargetTestBase {

  /**
   * The entity type manager prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity storage prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Field manager used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity repository used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The FeedsTarget plugin being tested.
   *
   * @var \Drupal\feeds\Feeds\Target\EntityReference
   */
  protected $targetPlugin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Entity type manager.
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->entityFieldManager->getFieldStorageDefinitions('referenceable_entity_type')->willReturn([]);
    $this->entityRepository = $this->prophesize(EntityRepositoryInterface::class);

    // Entity storage (needed for entity query's).
    $this->entityStorage = $this->prophesize(EntityStorageInterface::class);
    $this->entityTypeManager->getStorage('referenceable_entity_type')->willReturn($this->entityStorage);

    // Made-up entity type that we are referencing to.
    $referenceable_entity_type = $this->prophesize(EntityTypeInterface::class);
    $referenceable_entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')->willReturn(TRUE)->shouldBeCalled();
    $referenceable_entity_type->getKey('label')->willReturn('referenceable_entity_type label');
    $this->entityTypeManager->getDefinition('referenceable_entity_type')->willReturn($referenceable_entity_type)->shouldBeCalled();

    // EntityReference::prepareTarget() accesses the entity type manager from
    // the global container.
    // @see \Drupal\feeds\Feeds\Target\EntityReference::prepareTarget()
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $method = $this->getMethod('Drupal\feeds\Feeds\Target\EntityReference', 'prepareTarget')->getClosure();
    $field_definition_mock = $this->getMockFieldDefinition([
      'target_type' => 'referenceable_entity_type',
      'handler_settings' => ['target_bundles' => []],
    ]);
    $field_definition_mock->expects($this->once())->method('getSetting')->willReturn('referenceable_entity_type');

    $configuration = [
      'feed_type' => $this->createMock('Drupal\feeds\FeedTypeInterface'),
      'target_definition' => $method($field_definition_mock),
    ];
    $this->targetPlugin = new EntityReference($configuration, 'entity_reference', [], $this->entityTypeManager->reveal(), $this->entityFieldManager->reveal(), $this->entityRepository->reveal());
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetClass() {
    return EntityReference::class;
  }

  /**
   * @covers ::prepareTarget
   */
  public function testPrepareTarget() {
    $field_definition_mock = $this->getMockFieldDefinition();
    $field_definition_mock->expects($this->once())
      ->method('getSetting')
      ->will($this->returnValue('referenceable_entity_type'));

    $method = $this->getMethod($this->getTargetClass(), 'prepareTarget')->getClosure();
    $this->assertInstanceof(FieldTargetDefinition::class, $method($field_definition_mock));
  }

  /**
   * @covers ::prepareValue
   */
  public function testPrepareValue() {
    // Entity query.
    $entity_query = $this->prophesize(QueryInterface::class);
    $entity_query->range(0, 1)->willReturn($entity_query);
    $entity_query->condition("referenceable_entity_type label", 1)->willReturn($entity_query);
    $entity_query->execute()->willReturn([12, 13, 14]);
    $this->entityStorage->getQuery()->willReturn($entity_query)->shouldBeCalled();

    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');
    $values = ['target_id' => 1];
    $method(0, $values);
    $this->assertSame($values, ['target_id' => 12]);
  }

  /**
   * @covers ::prepareValue
   *
   * Tests prepareValue() without passing values.
   */
  public function testPrepareValueEmptyFeed() {
    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');
    $values = ['target_id' => ''];
    $this->expectException(EmptyFeedException::class);
    $method(0, $values);
  }

  /**
   * @covers ::prepareValue
   *
   * Tests prepareValue() method without match.
   */
  public function testPrepareValueReferenceNotFound() {
    // Entity query.
    $entity_query = $this->prophesize(QueryInterface::class);
    $entity_query->range(0, 1)->willReturn($entity_query);
    $entity_query->condition("referenceable_entity_type label", 1)->willReturn($entity_query);
    $entity_query->execute()->willReturn([]);
    $this->entityStorage->getQuery()->willReturn($entity_query)->shouldBeCalled();

    $method = $this->getProtectedClosure($this->targetPlugin, 'prepareValue');
    $values = ['target_id' => 1];
    $this->expectException(ReferenceNotFoundException::class, "Referenced entity not found for field <em class=\"placeholder\">referenceable_entity_type label</em> with value <em class=\"placeholder\">1</em>.");
    $method(0, $values);
  }

}
