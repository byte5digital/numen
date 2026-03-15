<?php return array (
  'types' => 
  array (
    'DateTime' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DateTime',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A datetime string with format `Y-m-d H:i:s`, e.g. `2018-05-23 13:43:32`.',
        'block' => false,
      ),
    ),
    'JSON' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'JSON',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\JSON',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Arbitrary data encoded in JavaScript Object Notation. See https://www.json.org/.',
        'block' => false,
      ),
    ),
    'Query' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Query',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentBySlug',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'slug',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'locale',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'StringValue',
                'value' => 'en',
                'block' => false,
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceSlug',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\ContentBySlugQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '30',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contents',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceSlug',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'locale',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'contentType',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'status',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            4 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            5 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentPaginator',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\ContentsQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '60',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Space',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'spaces',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Space',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'all',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '30',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentTypes',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentType',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\ContentTypesQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '300',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentVersion',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentVersion',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\ContentVersion',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'personas',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Persona',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\PersonasQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipelines',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentPipeline',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\PipelinesQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipelineRun',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\PipelineRun',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mediaAssets',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'after',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'MediaAssetConnection',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\MediaAssetsQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'vocabularies',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Vocabulary',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\VocabulariesQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '300',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'briefs',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'status',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'BriefStatus',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'after',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentBriefConnection',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\BriefsQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'webhooks',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Webhook',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\WebhooksQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pages',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'after',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PageConnection',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\PagesQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '60',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    'Space' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Space',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'settings',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'api_config',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentTypes',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentType',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'contentTypes',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '300',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contents',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'status',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'locale',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentPaginator',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\SpaceContentsQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    'ContentType' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentType',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'schema',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'generation_config',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'seo_config',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contents',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Content',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    'Content' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Content',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_type_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'locale',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'canonical_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxonomy',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'metadata',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hero_image_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'published_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'expires_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'refresh_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scheduled_publish_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleted_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        18 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentType',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentType',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'contentType',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'contentType',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        19 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currentVersion',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentVersion',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'currentVersion',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'currentVersion',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        20 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'draftVersion',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentVersion',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'draftVersion',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'draftVersion',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        21 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'heroImage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'MediaAsset',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'heroImage',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'heroImage',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        22 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxonomyTerms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'TaxonomyTerm',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'taxonomyTerms',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        23 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentVersion',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    'ContentVersion' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentVersion',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'version_number',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'label',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'parent_version_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'excerpt',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'body',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'body_format',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'structured_fields',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'seo_data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'author_type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'author_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'change_reason',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ai_metadata',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'quality_score',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'seo_score',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        18 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scheduled_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        19 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_hash',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        20 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'locked_by',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        21 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'locked_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        22 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        23 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        24 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Content',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        25 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'blocks',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentBlock',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'blocks',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    'ContentPaginator' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentPaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Content',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PaginatorInfo' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PaginatorInfo',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'currentPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'firstItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hasMorePages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lastItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lastPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'perPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'total',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'ContentStatus' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentStatus',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'DRAFT',
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'PUBLISHED',
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ARCHIVED',
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'SCHEDULED',
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'BriefStatus' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'BriefStatus',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'PENDING',
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'IN_PROGRESS',
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'COMPLETED',
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CANCELLED',
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PipelineRunStatus' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PipelineRunStatus',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'QUEUED',
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'RUNNING',
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'PAUSED_FOR_REVIEW',
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'COMPLETED',
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'FAILED',
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'CANCELLED',
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'MediaSource' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'MediaSource',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'UPLOAD',
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'AI_GENERATED',
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'EXTERNAL',
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'IMPORTED',
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PageInfo' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageInfo',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hasNextPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hasPreviousPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'startCursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'endCursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PipelineRunEdge' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PipelineRunEdge',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'node',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PipelineRun',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PipelineRunConnection' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PipelineRunConnection',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'edges',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PipelineRunEdge',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pageInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PageInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalCount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'ContentBriefEdge' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentBriefEdge',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'node',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentBrief',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'ContentBriefConnection' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentBriefConnection',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'edges',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ContentBriefEdge',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pageInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PageInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalCount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'MediaAssetEdge' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'MediaAssetEdge',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'node',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'MediaAsset',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'MediaAssetConnection' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'MediaAssetConnection',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'edges',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'MediaAssetEdge',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pageInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PageInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalCount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PageEdge' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageEdge',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'node',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Page',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cursor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'PageConnection' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageConnection',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'edges',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PageEdge',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pageInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PageInfo',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'totalCount',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'Persona' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Persona',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'role',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'capabilities',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_active',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A Persona defines an AI character with a role and capabilities. system_prompt is intentionally excluded.',
        'block' => false,
      ),
    ),
    'ContentPipeline' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentPipeline',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'stages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_active',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'runs',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'defaultValue' => 
              array (
                'kind' => 'IntValue',
                'value' => '20',
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'after',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PipelineRunConnection',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Queries\\PipelineRunsQuery',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'complexity',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Complexity\\PaginatedComplexity',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A ContentPipeline defines an automated workflow for content creation.',
        'block' => false,
      ),
    ),
    'PipelineRun' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PipelineRun',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipeline_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'PipelineRunStatus',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'current_stage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'stage_results',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'started_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'completed_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipeline',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentPipeline',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'pipeline',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'pipeline',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A PipelineRun is a single execution of a ContentPipeline.',
        'block' => false,
      ),
    ),
    'ContentBrief' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentBrief',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_type_slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'target_locale',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'target_keywords',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priority',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'BriefStatus',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'due_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A ContentBrief is a specification for content to be created.',
        'block' => false,
      ),
    ),
    'MediaAsset' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'MediaAsset',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'filename',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mime_type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'size_bytes',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'source',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'MediaSource',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'alt_text',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'caption',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tags',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'width',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'height',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'url',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_public',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A MediaAsset represents an uploaded or AI-generated media file.',
        'block' => false,
      ),
    ),
    'Vocabulary' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Vocabulary',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'terms',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'TaxonomyTerm',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'terms',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'cache',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '300',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'maxAge',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A Vocabulary is a named set of taxonomy terms.',
        'block' => false,
      ),
    ),
    'TaxonomyTerm' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'TaxonomyTerm',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'vocabulary_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'parent_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'depth',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'vocabulary',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Vocabulary',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'vocabulary',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'TaxonomyTerm',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A TaxonomyTerm is a single entry within a Vocabulary.',
        'block' => false,
      ),
    ),
    'Webhook' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Webhook',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'url',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'events',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'is_active',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A Webhook delivers event notifications to an external URL.',
        'block' => false,
      ),
    ),
    'Page' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Page',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentStatus',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'template',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Space',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'space',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'components',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PageComponent',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'components',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A Page represents a structured content page built from components.',
        'block' => false,
      ),
    ),
    'PageComponent' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageComponent',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'page_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'sort_order',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'page',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Page',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A PageComponent is a content block within a Page.',
        'block' => false,
      ),
    ),
    'ContentBlock' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ContentBlock',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'version_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slot',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'sort_order',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'version',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ContentVersion',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsTo',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'version',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'with',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'version',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'relation',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A ContentBlock is a typed block attached to a ContentVersion.',
        'block' => false,
      ),
    ),
    'CreateContentInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CreateContentInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_type_id',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'body',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'locale',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'defaultValue' => 
          array (
            'kind' => 'StringValue',
            'value' => 'en',
            'block' => false,
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'defaultValue' => 
          array (
            'kind' => 'StringValue',
            'value' => 'draft',
            'block' => false,
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxonomy_term_ids',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hero_image_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'UpdateContentInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'UpdateContentInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'slug',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'body',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'taxonomy_term_ids',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'hero_image_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'CreateBriefInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'CreateBriefInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'space_id',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content_type_slug',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'target_locale',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'defaultValue' => 
          array (
            'kind' => 'StringValue',
            'value' => 'en',
            'block' => false,
          ),
          'directives' => 
          array (
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'target_keywords',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priority',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'defaultValue' => 
          array (
            'kind' => 'StringValue',
            'value' => 'normal',
            'block' => false,
          ),
          'directives' => 
          array (
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'persona_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipeline_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'UpdateBriefInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'UpdateBriefInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'priority',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'UpdateMediaAssetInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'UpdateMediaAssetInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'alt_text',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'caption',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tags',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'Mutation' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Mutation',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'createContent',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CreateContentInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'create',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\CreateContent',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updateContent',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'UpdateContentInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'update',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\UpdateContent',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'publishContent',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'publish',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\PublishContent',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'unpublishContent',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'unpublish',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\UnpublishContent',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleteContent',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'delete',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\Content',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DeleteContent',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'createBrief',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'CreateBriefInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentBrief',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'create',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\ContentBrief',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\CreateBrief',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updateBrief',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'UpdateBriefInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ContentBrief',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'update',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\ContentBrief',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\UpdateBrief',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'triggerPipeline',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'pipelineId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'contentId',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'trigger',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\ContentPipeline',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\TriggerPipeline',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'approvePipelineRun',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'approve',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\PipelineRun',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\ApprovePipelineRun',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rejectPipelineRun',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'reason',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'reject',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\PipelineRun',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\RejectPipelineRun',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updateMediaAsset',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'UpdateMediaAssetInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'MediaAsset',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'update',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\MediaAsset',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\UpdateMediaAsset',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleteMediaAsset',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'MediaAsset',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'can',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'delete',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'find',
                  ),
                ),
                2 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\Models\\MediaAsset',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Mutations\\DeleteMediaAsset',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    'Subscription' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Subscription',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentPublished',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'subscription',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Subscriptions\\ContentPublished',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'class',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'contentUpdated',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'contentId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Content',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'subscription',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Subscriptions\\ContentUpdated',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'class',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipelineRunUpdated',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'runId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'subscription',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Subscriptions\\PipelineRunUpdated',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'class',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pipelineRunCompleted',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'spaceId',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'PipelineRun',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'subscription',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'App\\GraphQL\\Subscriptions\\PipelineRunCompleted',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'class',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    'SortOrder' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 301,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 91,
          'end' => 100,
        ),
        'kind' => 'Name',
        'value' => 'SortOrder',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 127,
            'end' => 189,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 186,
              'end' => 189,
            ),
            'kind' => 'Name',
            'value' => 'ASC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 127,
              'end' => 161,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in ascending order.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 215,
            'end' => 279,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 275,
              'end' => 279,
            ),
            'kind' => 'Name',
            'value' => 'DESC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 215,
              'end' => 250,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in descending order.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 65,
        ),
        'kind' => 'StringValue',
        'value' => 'Directions for ordering a list of records.',
        'block' => false,
      ),
    ),
    'OrderByRelationAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 276,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 125,
          'end' => 157,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 184,
            'end' => 254,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 232,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 233,
                'end' => 254,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 234,
                  'end' => 238,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 239,
                    'end' => 253,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 246,
                      'end' => 253,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 239,
                      'end' => 244,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 184,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 99,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation without specifying a column.',
        'block' => false,
      ),
    ),
    'OrderByRelationWithColumnAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 616,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 123,
          'end' => 165,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationWithColumnAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 192,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 230,
            ),
            'kind' => 'Name',
            'value' => 'AVG',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 231,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 232,
                  'end' => 236,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 237,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 244,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'avg',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 237,
                      'end' => 242,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 192,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Average.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 276,
            'end' => 334,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 311,
              'end' => 314,
            ),
            'kind' => 'Name',
            'value' => 'MIN',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 315,
                'end' => 334,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 316,
                  'end' => 320,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 321,
                    'end' => 333,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 328,
                      'end' => 333,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'min',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 321,
                      'end' => 326,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 276,
              'end' => 286,
            ),
            'kind' => 'StringValue',
            'value' => 'Minimum.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 360,
            'end' => 418,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 395,
              'end' => 398,
            ),
            'kind' => 'Name',
            'value' => 'MAX',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 399,
                'end' => 418,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 400,
                  'end' => 404,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 405,
                    'end' => 417,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 412,
                      'end' => 417,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'max',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 405,
                      'end' => 410,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 360,
              'end' => 370,
            ),
            'kind' => 'StringValue',
            'value' => 'Maximum.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 444,
            'end' => 498,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 475,
              'end' => 478,
            ),
            'kind' => 'Name',
            'value' => 'SUM',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 479,
                'end' => 498,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 480,
                  'end' => 484,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 485,
                    'end' => 497,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 492,
                      'end' => 497,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'sum',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 485,
                      'end' => 490,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 444,
              'end' => 450,
            ),
            'kind' => 'StringValue',
            'value' => 'Sum.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 524,
            'end' => 594,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 572,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 573,
                'end' => 594,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 574,
                  'end' => 578,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 579,
                    'end' => 593,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 586,
                      'end' => 593,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 579,
                      'end' => 584,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 524,
              'end' => 542,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 97,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation that may specify a column.',
        'block' => false,
      ),
    ),
    'OrderByClause' => 
    array (
      'loc' => 
      array (
        'start' => 12,
        'end' => 278,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 67,
          'end' => 80,
        ),
        'kind' => 'Name',
        'value' => 'OrderByClause',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 99,
            'end' => 170,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 155,
              'end' => 161,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 163,
              'end' => 170,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 163,
                'end' => 169,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 163,
                  'end' => 169,
                ),
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 99,
              'end' => 138,
            ),
            'kind' => 'StringValue',
            'value' => 'The column that is used for ordering.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 188,
            'end' => 264,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 247,
              'end' => 252,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 254,
              'end' => 264,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 254,
                'end' => 263,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 254,
                  'end' => 263,
                ),
                'kind' => 'Name',
                'value' => 'SortOrder',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 188,
              'end' => 230,
            ),
            'kind' => 'StringValue',
            'value' => 'The direction that is used for ordering.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 12,
          'end' => 48,
        ),
        'kind' => 'StringValue',
        'value' => 'Allows ordering a list of records.',
        'block' => false,
      ),
    ),
    'Trashed' => 
    array (
      'loc' => 
      array (
        'start' => 25,
        'end' => 530,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 128,
          'end' => 135,
        ),
        'kind' => 'Name',
        'value' => 'Trashed',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 166,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 225,
              'end' => 229,
            ),
            'kind' => 'Name',
            'value' => 'ONLY',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 230,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 231,
                  'end' => 235,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 236,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 243,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'only',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 236,
                      'end' => 241,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 166,
              'end' => 196,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return trashed results.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 280,
            'end' => 380,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 355,
              'end' => 359,
            ),
            'kind' => 'Name',
            'value' => 'WITH',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 360,
                'end' => 380,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 361,
                  'end' => 365,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 366,
                    'end' => 379,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 373,
                      'end' => 379,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'with',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 366,
                      'end' => 371,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 280,
              'end' => 326,
            ),
            'kind' => 'StringValue',
            'value' => 'Return both trashed and non-trashed results.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 410,
            'end' => 504,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 473,
              'end' => 480,
            ),
            'kind' => 'Name',
            'value' => 'WITHOUT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 481,
                'end' => 504,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 482,
                  'end' => 486,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 487,
                    'end' => 503,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 494,
                      'end' => 503,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'without',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 487,
                      'end' => 492,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 410,
              'end' => 444,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return non-trashed results.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 25,
          'end' => 98,
        ),
        'kind' => 'StringValue',
        'value' => 'Specify if you want to include or exclude trashed results from a query.',
        'block' => false,
      ),
    ),
  ),
  'directives' => 
  array (
  ),
  'classNameToObjectTypeName' => 
  array (
  ),
  'schemaExtensions' => 
  array (
  ),
  'hash' => '6f59a8bb8f8a0c20cffb79c8ec7fb18987402b67714319d3a256089e4deb4063',
);