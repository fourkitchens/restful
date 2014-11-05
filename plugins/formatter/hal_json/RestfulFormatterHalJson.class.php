<?php

/**
 * @file
 * Contains RestfulFormatterHalJson.
 */

class RestfulFormatterHalJson extends \RestfulFormatterBase implements \RestfulFormatterInterface {
  /**
   * Content Type
   *
   * @var string
   */
  protected $contentType = 'application/hal+json; charset=utf-8';

  /**
   * {@inheritdoc}
   */
  public function prepare(array $data) {
    // If we're returning an error then set the content type to
    // 'application/problem+json; charset=utf-8'.
    if (!empty($data['status']) && floor($data['status'] / 100) != 2) {
      $this->contentType = 'application/problem+json; charset=utf-8';
      return $data;
    }
    // Here we get the data after calling the backend storage for the resources.
    $context = array();
    $context['handler'] = get_class($this->handler);
    $context['resource'] = $this->handler->plugin['resource'];
    //$context['request'] = $this->handler->request['q'];
    drupal_alter('restfulFormatterHal_prepare', $data, $context);
    $output = $data;

    if (isset($data['_embedded'])) {
     $this->addEmbeddedHateos($output);
    }

    if (!empty($this->handler)) {
      if (method_exists($this->handler, 'isListRequest') && !$this->handler->isListRequest()) {
        return $output;
      }
      if (method_exists($this->handler, 'getTotalCount')) {
        // Get the total number of items for the current request without pagination.
        $output['count'] = $this->handler->getTotalCount();
      }

      // Add HATEOAS to the output.
      $this->addHateoas($output);
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $structured_data) {
    return drupal_json_encode($structured_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentTypeHeader() {
    return $this->contentType;
  }

  /**
   * Add HATEOAS links to items in a list
   */
  protected function addEmbeddedHateos(array &$data) {
    if (!$this->handler) {
      return;
    }
    $request = $this->handler->getRequest();

    $resource = $this->handler->plugin['resource'];
    foreach ($data['_embedded']['fk:'. $resource] as &$item) {
      $link= new stdClass();
      $link->href = $base_url .'/'. $request['q'] . '/'. $item['id'];
      $item['_links'] = array('self' => $link);
    }
   $foo = $data;
  }
  /**
   * Add HATEOAS links to list of item.
   *
   * @param $data
   *   The data array after initial massaging.
   */
  protected function addHateoas(array &$data) {
    if (!$this->handler) {
      return;
    }
    $request = $this->handler->getRequest();

    if (!$data['_links']) {
      $data['_links'] = array();
    }
    $page = !empty($request['page']) ? $request['page'] : 1;

    if ($page > 1) {
      $request['page'] = $page - 1;
      $previous = new stdClass();
      $previous->href = $this->handler->getUrl();
      $data['_links']['previous'] = $previous;
    }

    // We know that there are more pages if the total count is bigger than the
    // number of items of the current request plus the number of items in
    // previous pages.
    $items_per_page = $this->handler->getRange();
    $previous_items = ($page - 1) * $items_per_page;
    if ($data['count'] > count($data['_embedded']) + $previous_items) {
      $request['page'] = $page + 1;
      $next = new stdClass();
      $next->href = $this->handler->getUrl($request);
      $data['_links']['next'] = $next;
    }

    // add curies
    global $base_url;
    $curies = array();
    $curie = new stdClass();
    $curie->name = "fk";
    $curie->href = $base_url . "/api-docs/{rel}";
    $curie->templated = true;
    $curies[] = $curie;
    $data['_links']['curies'] = $curies;
    $curied_links = array();
    $dontDocumentMe = array('curies', 'self');

    foreach ($data['_links'] as $rel => $link) {
      if (!in_array($rel, $dontDocumentMe)) {
        $curied_links[$curie->name . ':' . $rel] = $link;
      }
      else {
        $curied_links[$rel] = $link;
      }
    }
    $data['_links'] = $curied_links;
  }
}
