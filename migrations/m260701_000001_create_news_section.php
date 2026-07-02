<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\fields\PlainText;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\GqlSchema;
use craft\models\GqlToken;
use craft\models\Section;
use craft\models\Section_SiteSettings;

class m260701_000001_create_news_section extends Migration
{
    public function safeUp(): void
    {
        $craft = Craft::$app;

        // 1. Body field
        $bodyField = $craft->fields->getFieldByHandle('body');
        if (!$bodyField) {
            $bodyField = new PlainText([
                'name'              => 'Body',
                'handle'            => 'body',
                'translationMethod' => \craft\base\Field::TRANSLATION_METHOD_NONE,
                'multiline'         => true,
                'initialRows'       => 4,
            ]);
            if (!$craft->fields->saveField($bodyField)) {
                throw new \Exception('Could not save body field: ' . implode(', ', $bodyField->getFirstErrors()));
            }
            echo "  Created field: body\n";
        } else {
            echo "  Field already exists: body\n";
        }

        // 2. Entry type
        $newsEntryType = $craft->entries->getEntryTypeByHandle('news');
        if (!$newsEntryType) {
            $newsEntryType = new EntryType([
                'name'          => 'News',
                'handle'        => 'news',
                'hasTitleField' => true,
            ]);
            $fieldLayout = new FieldLayout(['type' => Entry::class]);
            $tab = new FieldLayoutTab(['name' => 'Content']);
            $fieldLayout->setTabs([$tab]);
            $tab->setElements([new TitleField(), new CustomField($bodyField)]);
            $newsEntryType->setFieldLayout($fieldLayout);

            if (!$craft->entries->saveEntryType($newsEntryType)) {
                throw new \Exception('Could not save entry type: ' . implode(', ', $newsEntryType->getFirstErrors()));
            }
            echo "  Created entry type: news\n";
        } else {
            echo "  Entry type already exists: news\n";
        }

        // 3. Section
        $section = $craft->entries->getSectionByHandle('news');
        if (!$section) {
            $siteId  = $craft->sites->getPrimarySite()->id;
            $section = new Section([
                'name'              => 'News',
                'handle'            => 'news',
                'type'              => Section::TYPE_CHANNEL,
                'enableVersioning'  => true,
                'propagationMethod' => Section::PROPAGATION_METHOD_ALL,
                'siteSettings'      => [
                    new Section_SiteSettings([
                        'siteId'           => $siteId,
                        'enabledByDefault' => true,
                        'hasUrls'          => true,
                        'uriFormat'        => 'news/{slug}',
                        'template'         => '',
                    ]),
                ],
            ]);
            $section->setEntryTypes([$newsEntryType]);

            if (!$craft->entries->saveSection($section)) {
                throw new \Exception('Could not save section: ' . implode(', ', $section->getFirstErrors()));
            }
            echo "  Created section: news\n";
        } else {
            echo "  Section already exists: news\n";
        }

        // 4. GraphQL schema
        // Craft 5 scope format:
        //   sites.{siteUid}:read       — grants site access
        //   sections.{sectionUid}:read — grants read access + enables entries query
        $gqlSchema = null;
        foreach ($craft->gql->getSchemas() as $s) {
            if ($s->name === 'Frontend') { $gqlSchema = $s; break; }
        }
        if (!$gqlSchema) {
            $gqlSchema = new GqlSchema([
                'name'     => 'Frontend',
                'isPublic' => false,
                'scope'    => [
                    'sites.' . $craft->sites->getPrimarySite()->uid . ':read',
                    'sections.' . $section->uid . ':read',
                ],
            ]);
            if (!$craft->gql->saveSchema($gqlSchema)) {
                throw new \Exception('Could not save GQL schema: ' . implode(', ', $gqlSchema->getFirstErrors()));
            }
            echo "  Created GQL schema: Frontend\n";
        } else {
            echo "  GQL schema already exists: Frontend\n";
        }

        // 5. Bearer token — printed to deploy log on first run; copy to CRAFT_GQL_TOKEN env var
        $existingToken = $craft->gql->getTokenByName('frontend-token');
        if (!$existingToken) {
            $token = new GqlToken([
                'name'        => 'frontend-token',
                'accessToken' => StringHelper::randomString(48),
                'enabled'     => true,
                'schemaId'    => $gqlSchema->id,
            ]);
            if (!$craft->gql->saveToken($token)) {
                throw new \Exception('Could not save GQL token: ' . implode(', ', $token->getFirstErrors()));
            }
            echo "\n  *** BEARER TOKEN (copy to CRAFT_GQL_TOKEN): {$token->accessToken} ***\n\n";
        } else {
            echo "  GQL token already exists: frontend-token\n";
        }
    }

    public function safeDown(): bool
    {
        return false;
    }
}
