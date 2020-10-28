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
            'variable' => $this->getVariables(),
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
                    'item' => $routes->map(static function ($route) {
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

                        $queryPart = empty($queryParameters) ? '' : ('?' . str_replace(
                                array('%7B%7B', '%7D%7D'),
                                array('{{', '}}'),
                                http_build_query(
                                    $queryParameters
                                )
                            ));

                        $routeParts = parse_url($routeUri);



                        $rawURL = str_replace(
                                sprintf('%s://%s%s', $routeParts['scheme'] ?? '', $routeParts['host'] ?? '', (isset($routeParts['port']) && !empty($routeParts['port'])) ? (':' . $routeParts['port']) : ''),
                                '{{endpoint}}',
                                $routeUri
                            ) . $queryPart;

                        $routeParts['scheme'] = '';
                        $routeParts['port'] = '';
                        $routeParts['host'] = '{{endpoint}}';

                        $protocol = $routeParts['scheme'];

                        $result = [
                            'name' => $route['title'] != '' ? $route['title'] : $route['uri'],
                            'request' => [
                                'url' => array_filter([
                                    'raw'      => $rawURL,
                                    'protocol' => $protocol,
                                    'host'     => explode('.', $routeParts['host']),
                                    'path'     => explode('/', trim($routeParts['path'], '/')),
                                    'query'    => $queryParams,
                                    'port'     => $routeParts['port'] ?? null,
                                ], static function ($item) {
                                    return $item !== null;
                                }),
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

    private function getVariables(): array
    {
        static $variables = null;
        if (null === $variables) {
            $variables = [];
            $vars = config('apidoc.variables', array());
            if (!array_key_exists('endpoint', $vars)) {
                $vars['endpoint'] = env('APP_URL', '');
            }

            foreach ($vars as $key => $value) {
                $variables[] = [
                    'id'    => Uuid::uuid4()->toString(),
                    'key'   => $key,
                    'value' => is_scalar($value) ? $value : serialize($value),
                    'type'  => 'string',
                ];
            }
        }

        return $variables;
    }
}
