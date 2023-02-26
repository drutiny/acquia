<?php

namespace Drutiny\Acquia\Source;

use Drutiny\LanguageManager;
use Drutiny\Policy;
use Drutiny\Profile;
use Drutiny\ProfileSource\ProfileSourceInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Load profiles from CSKB.
 */
class ProfileSource extends SourceBase implements ProfileSourceInterface
{
    public const API_ENDPOINT = 'jsonapi/node/profile';

    /**
     * {@inheritdoc}
     */
    public function getList(LanguageManager $languageManager): array
    {
        $list = [];
        $query = $this->getRequestParams();
        $query['query']['fields[node--profile]'] = 'title,field_name';

        foreach ($this->client->getList($this->getApiPrefix().self::API_ENDPOINT, $query) as $item) {
            $list[$item['field_name']] = [
        'name' => $item['field_name'],
        'title' => $item['title'],
        'uuid' => $item['uuid'],
        'language' => $languageManager->getCurrentLanguage(),
      ];
        }
        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $definition): Profile
    {
        $query = $this->getRequestParams();
        $endpoint = $this->getApiPrefix().self::API_ENDPOINT.'/'.$definition['uuid'];
        $response = $this->client->get($endpoint, $query);

        $fields = $response['data']['attributes'];
        $policies = Yaml::parse($fields['field_policies']);

        foreach ($policies as $policy_name => $info) {
            switch ($info['severity'] ?? false) {
                case Policy::SEVERITY_LOW:
                    $policies[$policy_name]['severity'] = 'low';
                    break;
                case Policy::SEVERITY_NORMAL:
                    $policies[$policy_name]['severity'] = 'normal';
                    break;
                case Policy::SEVERITY_HIGH:
                    $policies[$policy_name]['severity'] = 'high';
                    break;
                case Policy::SEVERITY_CRITICAL:
                    $policies[$policy_name]['severity'] = 'critical';
                    break;
                default:
                    $policies[$policy_name]['severity'] = 'normal';
                    break;
            }
        }

        $definition = [
          'title' => $fields['title'],
          'name' => $fields['field_name'],
          'uuid' => $definition['uuid'],
          'description' => $fields['field_description'],
          'policies' => $policies,
          'include' => $fields['field_include'],
          'format' => [
            'html' => [
              'template' => $fields['field_html_template'],
              'content' => $fields['field_html_content'],
            ]
          ]
        ];

        if (isset($fields['field_dependencies'])) {
            $dependencies = Yaml::parse($fields['field_dependencies']);
            foreach ($dependencies as $policy_name => $info) {
                switch ($info['severity'] ?? false) {
                    case Policy::SEVERITY_LOW:
                        $dependencies[$policy_name]['severity'] = 'low';
                        break;
                    case Policy::SEVERITY_NORMAL:
                        $dependencies[$policy_name]['severity'] = 'normal';
                        break;
                    case Policy::SEVERITY_HIGH:
                        $dependencies[$policy_name]['severity'] = 'high';
                        break;
                    case Policy::SEVERITY_CRITICAL:
                        $dependencies[$policy_name]['severity'] = 'critical';
                        break;
                    default:
                        $dependencies[$policy_name]['severity'] = 'normal';
                        break;
                }
            }
            $definition['dependencies'] = $dependencies;
        }

        try {
            $definition['excluded_policies'] = !empty($fields['field_excluded_policies']) ? Yaml::parse($fields['field_excluded_policies']) : [];
            // $profile_fields['format']['html']['content'] = Yaml::parse($fields['field_html_content']);
        } catch (ParseException $e) {
        }

        return $this->profileFactory->create($definition);
    }
}
