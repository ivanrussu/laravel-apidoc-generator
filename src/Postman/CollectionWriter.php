<?php

namespace Mpociot\ApiDoc\Postman;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class CollectionWriter
{
    /**
     * @var Collection
     */
    private $routeGroups;

    /**
     * CollectionWriter constructor.
     *
     * @param Collection $routeGroups
     */
    public function __construct(Collection $routeGroups)
    {
        $this->routeGroups = $routeGroups;
    }

    public function getCollection()
    {
        URL::forceRootUrl(config('app.url'));

        $collection = [
            'variables' => [],
            'info' => [
                'name' => config('apidoc.postman.name') ?: config('app.name').' API',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => config('apidoc.postman.description') ?: '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routeGroups->map(function ($routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => '',
                    'item' => $routes->map(function ($route) {
                        $mode = $route['methods'][0] === 'PUT' ? 'urlencoded' : 'formdata';

                        $mode = isset($route['jsonRequest']) ? 'raw' : $mode;

                        $routeUri = url($route['uri']);

                        $queryParameters = array_map(
                            static function ($val) {
                                return $val['value'] ?? '';
                            },
                            $route['queryParameters'] ?? []
                        );
                        $queryParams = [];

                        foreach ($queryParameters as $key => $value) {
                            $queryParams[] = [
                                'key' => $key,
                                'value' => $value,
                            ];
                        }

                        $queryPart = empty($queryParameters) ? '' : ('?' . http_build_query($queryParameters));

                        $routeParts = parse_url($routeUri);


                        $protocol = $routeParts['scheme'];

                        $result = [
                            'name' => $route['title'] != '' ? $route['title'] : $route,
                            'request' => [
                                'url'    => [
                                    'raw' => $routeUri . $queryPart,
                                    'protocol' => $protocol,
                                    'host' => explode('.', $routeParts['host']),
                                    'path' => explode('/', trim($routeParts['path'], '/')),
                                    'query' => $queryParams,
                                ],
                                'method' => $route['methods'][0],
                                'header' => $mode === 'raw'
                                    ? [
                                        [
                                            'key'   => 'Content-Type',
                                            'name'  => 'Content-Type',
                                            'value' => 'application/json',
                                            'type'  => 'text',
                                        ],
                                    ]
                                    : [],
                                'body'   => [
                                    'mode' => $mode,
                                    $mode => $mode === 'raw' ? $route['jsonRequest'] : collect($route['bodyParameters'])->map(function ($parameter, $key) {
                                        return [
                                            'key' => $key,
                                            'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                            'type' => 'text',
                                            'enabled' => true,
                                        ];
                                    })->values()->toArray(),
                                ],
                                'description' => $route['description'],
                                'response' => [],
                            ],
                        ];

                        if ($mode === 'raw') {
                            $result['protocolProfileBehavior'] = [
                                'disableBodyPruning' => true
                            ];
                        }

                        return $result;
                    })->toArray(),
                ];
            })->values()->toArray(),
        ];

        return json_encode($collection);
    }
}
