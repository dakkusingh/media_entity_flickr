<?php

namespace Drupal\media_entity_flickr\Plugin\MediaEntity\Type;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_entity\MediaInterface;
use Drupal\media_entity\MediaTypeBase;
use Drupal\media_entity\MediaTypeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Flickr.
 *
 * @MediaType(
 *   id = "flickr",
 *   label = @Translation("Flickr"),
 *   description = @Translation("Provides business logic and metadata for Flickr.")
 * )
 */
class Flickr extends MediaTypeBase {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $config_factory->get('media_entity.settings'));
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'use_flickr_api' => FALSE,
    ];
  }

  /**
   * List of validation regular expressions.
   *
   * @var array
   */
  public static $validationRegexp = array(
    '@((http|https):){0,1}//(www\.){0,1}flickr\.com/photos/(?<username>[^\s]+)/(?<shortcode>[a-z0-9_-]+)@i' => 'shortcode',
  );

  /**
   * {@inheritdoc}
   */
  public function providedFields() {
    $fields = array(
      'shortcode' => $this->t('Flickr shortcode'),
      'username' => $this->t('Author of the post'),
    );

    if ($this->configuration['use_flickr_api']) {
      $fields += array(
        'id' => $this->t('Media ID'),
        'type' => $this->t('Media type: image or video'),
        'thumbnail' => $this->t('Link to the thumbnail'),
        'thumbnail_local' => $this->t("Copies thumbnail locally and return it's URI"),
        'thumbnail_local_uri' => $this->t('Returns local URI of the thumbnail'),
        'caption' => $this->t('Caption'),
        'tags' => $this->t('Tags'),
      );
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getField(MediaInterface $media, $name) {
    $matches = $this->matchRegexp($media);

    if (!$matches['shortcode']) {
      return FALSE;
    }

    if ($name == 'shortcode') {
      return $matches['shortcode'];
    }

    if (!$matches['username']) {
      return FALSE;
    }

    if ($name == 'username') {
      return $matches['shortcode'];
    }

    // If we have auth settings return the other fields.
    if ($this->configuration['use_flickr_api'] && $flickr = $this->fetchFlickr($matches['shortcode'])) {
      switch ($name) {
        case 'id':
          if (isset($flickr->id)) {
            return $flickr->id;
          }
          return FALSE;

        case 'type':
          if (isset($flickr->type)) {
            return $flickr->type;
          }
          return FALSE;

        case 'thumbnail':
          if (isset($flickr->images->thumbnail->url)) {
            return $flickr->images->thumbnail->url;
          }
          return FALSE;

        case 'thumbnail_local':
          if (isset($flickr->images->thumbnail->url)) {
            $local_uri = $this->configFactory->get('media_entity_flickr.settings')->get('local_images') . '/' . $matches['shortcode'] . '.' . pathinfo($flickr->images->thumbnail->url, PATHINFO_EXTENSION);

            if (!file_exists($local_uri)) {
              file_prepare_directory($local_uri, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

              $image = file_get_contents($local_uri);
              file_unmanaged_save_data($image, $local_uri, FILE_EXISTS_REPLACE);

              return $local_uri;
            }
          }
          return FALSE;

        case 'thumbnail_local_uri':
          if (isset($flickr->images->thumbnail->url)) {
            return $this->configFactory->get('media_entity_flickr.settings')->get('local_images') . '/' . $matches['shortcode'] . '.' . pathinfo($flickr->images->thumbnail->url, PATHINFO_EXTENSION);
          }
          return FALSE;

        case 'username':
          if (isset($flickr->user->username)) {
            return $flickr->user->username;
          }
          return FALSE;

        case 'caption':
          if (isset($flickr->caption->text)) {
            return $flickr->caption->text;
          }
          return FALSE;

        case 'tags':
          if (isset($flickr->tags)) {
            return implode(" ", $flickr->tags);
          }
          return FALSE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $bundle = $form_state->getFormObject()->getEntity();
    $allowed_field_types = ['string', 'string_long', 'link'];
    foreach ($this->entityFieldManager->getFieldDefinitions('media', $bundle->id()) as $field_name => $field) {
      if (in_array($field->getType(), $allowed_field_types) && !$field->getFieldStorageDefinition()->isBaseField()) {
        $options[$field_name] = $field->getLabel();
      }
    }

    $form['source_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with source information'),
      '#description' => $this->t('Field on media entity that stores Flickr embed code or URL. You can create a bundle without selecting a value for this dropdown initially. This dropdown can be populated after adding fields to the bundle.'),
      '#default_value' => empty($this->configuration['source_field']) ? NULL : $this->configuration['source_field'],
      '#options' => $options,
    ];

    $form['use_flickr_api'] = [
      '#type' => 'select',
      '#title' => $this->t('Whether to use Flickr api to fetch photos or not.'),
      '#description' => $this->t("In order to use Flickr's api you have to create a developer account and an application. For more information consult the readme file."),
      '#default_value' => empty($this->configuration['use_flickr_api']) ? 0 : $this->configuration['use_flickr_api'],
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
    ];

    // @todo Evaluate if this should be a site-wide configuration.
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => empty($this->configuration['client_id']) ? NULL : $this->configuration['client_id'],
      '#states' => [
        'visible' => [
          ':input[name="type_configuration[flickr][use_flickr_api]"]' => ['value' => '1'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function attachConstraints(MediaInterface $media) {
    parent::attachConstraints($media);

    if (isset($this->configuration['source_field'])) {
      $source_field_name = $this->configuration['source_field'];
      if ($media->hasField($source_field_name)) {
        foreach ($media->get($source_field_name) as &$embed_code) {
          /** @var \Drupal\Core\TypedData\DataDefinitionInterface $typed_data */
          $typed_data = $embed_code->getDataDefinition();
          $typed_data->addConstraint('FlickrEmbedCode');
        }
      }
    }
  }

  /**
   * Runs preg_match on embed code/URL.
   *
   * @param MediaInterface $media
   *   Media object.
   *
   * @return array|bool
   *   Array of preg matches or FALSE if no match.
   *
   * @see preg_match()
   */
  protected function matchRegexp(MediaInterface $media) {
    $matches = array();
    //ksm($this->configuration['source_field']);
    if (isset($this->configuration['source_field'])) {
      $source_field = $this->configuration['source_field'];
      if ($media->hasField($source_field)) {
        $property_name = $media->{$source_field}->first()->mainPropertyName();
        foreach (static::$validationRegexp as $pattern => $key) {
          //ksm(array($pattern, $media->{$source_field}->{$property_name}));
          if (preg_match($pattern, $media->{$source_field}->{$property_name}, $matches)) {
            return $matches;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Get a single flickr photo.
   *
   * @param string $shortcode
   *   The flickr shortcode.
   */
  protected function fetchFlickr($shortcode) {
    $flickr = &drupal_static(__FUNCTION__);

    if (!isset($flickr)) {
      // Check for dependencies.
      // @todo There is perhaps a better way to do that.
      if (!class_exists('\Flickr\Flickr')) {
        drupal_set_message($this->t('Flickr library is not available. Consult the README.md for installation instructions.'), 'error');
        return;
      }

      if (!isset($this->configuration['client_id'])) {
        drupal_set_message($this->t('The client ID is not available. Consult the README.md for installation instructions.'), 'error');
        return;
      }
      if (empty($this->configuration['client_id'])) {
        drupal_set_message($this->t('The client ID is missing. Please add it in your Flickr settings.'), 'error');
        return;
      }
      $flickr_object = new FlickrApi();
      $flickr_object->setClientID($this->configuration['client_id']);
      $result = $flickr_object->getMediaByShortcode($shortcode)->getData();

      if ($result) {
        return $result;
      }
      else {
        throw new MediaTypeException(NULL, 'The media could not be retrieved.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultThumbnail() {
    return $this->config->get('icon_base') . '/flickr.png';
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    if ($local_image = $this->getField($media, 'thumbnail_local')) {
      return $local_image;
    }

    return $this->getDefaultThumbnail();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultName(MediaInterface $media) {
    // Try to get some fields that need the API, if not available, just use the
    // shortcode as default name.

    $username = $this->getField($media, 'username');
    $id = $this->getField($media, 'id');
    if ($username && $id) {
      return $username . ' - ' . $id;
    }
    else {
      $code = $this->getField($media, 'shortcode');
      if (!empty($code)) {
        return $code;
      }
    }

    return parent::getDefaultName($media);
  }

}
