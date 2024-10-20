<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Models\Node;
use App\Models\Objects\Endpoint;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Models\Database;
use App\Services\Databases\DatabaseManagementService;
use App\Services\Databases\DatabasePasswordService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use LogicException;
use App\Filament\Resources\ServerResource;
use App\Http\Controllers\Admin\ServersController;
use App\Services\Servers\RandomWordService;
use App\Services\Servers\SuspensionService;
use App\Services\Servers\TransferServerService;
use Filament\Actions;
use Filament\Forms;
use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Models\Egg;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\ServerDeletionService;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Validator;
use Webbingbrasil\FilamentCopyActions\Forms\Actions\CopyAction;

class EditServer extends EditRecord
{
    public ?Node $node = null;
    public ?Egg $egg = null;
    public array $ports = [];
    public array $eggDefaultPorts = [];

    protected static string $resource = ServerResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->persistTabInQueryString()
                    ->columns([
                        'default' => 2,
                        'sm' => 2,
                        'md' => 4,
                        'lg' => 6,
                    ])
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Information')
                            ->icon('tabler-info-circle')
                            ->schema([
                                Forms\Components\ToggleButtons::make('condition')
                                    ->label('Status')
                                    ->formatStateUsing(fn (Server $server) => $server->condition)
                                    ->options(fn ($state) => collect(array_merge(ContainerStatus::cases(), ServerState::cases()))
                                        ->filter(fn ($condition) => $condition->value === $state)
                                        ->mapWithKeys(fn ($state) => [$state->value => str($state->value)->replace('_', ' ')->ucwords()])
                                    )
                                    ->colors(collect(array_merge(ContainerStatus::cases(), ServerState::cases()))->mapWithKeys(
                                        fn ($status) => [$status->value => $status->color()]
                                    ))
                                    ->icons(collect(array_merge(ContainerStatus::cases(), ServerState::cases()))->mapWithKeys(
                                        fn ($status) => [$status->value => $status->icon()]
                                    ))
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 1,
                                        'lg' => 1,
                                    ]),

                                TextInput::make('name')
                                    ->prefixIcon('tabler-server')
                                    ->label('Display Name')
                                    ->suffixAction(Action::make('random')
                                        ->icon('tabler-dice-' . random_int(1, 6))
                                        ->action(function (Set $set, Get $get) {
                                            $egg = Egg::find($get('egg_id'));
                                            $prefix = $egg ? str($egg->name)->lower()->kebab() . '-' : '';

                                            $word = (new RandomWordService())->word();

                                            $set('name', $prefix . $word);
                                        }))
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->required()
                                    ->maxLength(255),

                                Select::make('owner_id')
                                    ->prefixIcon('tabler-user')
                                    ->label('Owner')
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 2,
                                    ])
                                    ->relationship('user', 'username')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                ToggleButtons::make('condition')
                                    ->label('Server Status')
                                    ->formatStateUsing(fn (Server $server) => $server->condition)
                                    ->options(fn ($state) => collect(array_merge(ContainerStatus::cases(), ServerState::cases()))
                                        ->filter(fn ($condition) => $condition->value === $state)
                                        ->mapWithKeys(fn ($state) => [$state->value => str($state->value)->replace('_', ' ')->ucwords()])
                                    )
                                    ->colors(collect(array_merge(ContainerStatus::cases(), ServerState::cases()))->mapWithKeys(
                                        fn ($status) => [$status->value => $status->color()]
                                    ))
                                    ->icons(collect(array_merge(ContainerStatus::cases(), ServerState::cases()))->mapWithKeys(
                                        fn ($status) => [$status->value => $status->icon()]
                                    ))
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 1,
                                        'lg' => 1,
                                    ]),

                                Textarea::make('description')
                                    ->label('Notes')
                                    ->columnSpanFull(),

                                TextInput::make('uuid')
                                    ->hintAction(CopyAction::make())
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->readOnly()
                                    ->dehydrated(false),
                                TextInput::make('uuid_short')
                                    ->label('Short UUID')
                                    ->hintAction(CopyAction::make())
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->readOnly()
                                    ->dehydrated(false),
                                TextInput::make('external_id')
                                    ->label('External ID')
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->maxLength(255),
                                Select::make('node_id')
                                    ->label('Node')
                                    ->relationship('node', 'name')
                                    ->columnSpan([
                                        'default' => 2,
                                        'sm' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->disabled(),
                            ]),

                        Tab::make('Environment')
                            ->icon('tabler-brand-docker')
                            ->schema([
                                Fieldset::make('Resource Limits')
                                    ->columns([
                                        'default' => 1,
                                        'sm' => 2,
                                        'md' => 3,
                                        'lg' => 3,
                                    ])
                                    ->schema([
                                        Grid::make()
                                            ->columns(4)
                                            ->columnSpanFull()
                                            ->schema([
                                                ToggleButtons::make('unlimited_mem')
                                                    ->label('Memory')->inlineLabel()->inline()
                                                    ->afterStateUpdated(fn (Set $set) => $set('memory', 0))
                                                    ->formatStateUsing(fn (Get $get) => $get('memory') == 0)
                                                    ->live()
                                                    ->options([
                                                        true => 'Unlimited',
                                                        false => 'Limited',
                                                    ])
                                                    ->colors([
                                                        true => 'primary',
                                                        false => 'warning',
                                                    ])
                                                    ->columnSpan(2),

                                                TextInput::make('memory')
                                                    ->dehydratedWhenHidden()
                                                    ->hidden(fn (Get $get) => $get('unlimited_mem'))
                                                    ->label('Memory Limit')->inlineLabel()
                                                    ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->numeric()
                                                    ->minValue(0),
                                            ]),

                                        Grid::make()
                                            ->columns(4)
                                            ->columnSpanFull()
                                            ->schema([
                                                ToggleButtons::make('unlimited_disk')
                                                    ->label('Disk Space')->inlineLabel()->inline()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('disk', 0))
                                                    ->formatStateUsing(fn (Get $get) => $get('disk') == 0)
                                                    ->options([
                                                        true => 'Unlimited',
                                                        false => 'Limited',
                                                    ])
                                                    ->colors([
                                                        true => 'primary',
                                                        false => 'warning',
                                                    ])
                                                    ->columnSpan(2),

                                                TextInput::make('disk')
                                                    ->dehydratedWhenHidden()
                                                    ->hidden(fn (Get $get) => $get('unlimited_disk'))
                                                    ->label('Disk Space Limit')->inlineLabel()
                                                    ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->numeric()
                                                    ->minValue(0),
                                            ]),

                                        Grid::make()
                                            ->columns(4)
                                            ->columnSpanFull()
                                            ->schema([
                                                ToggleButtons::make('unlimited_cpu')
                                                    ->label('CPU')->inlineLabel()->inline()
                                                    ->afterStateUpdated(fn (Set $set) => $set('cpu', 0))
                                                    ->formatStateUsing(fn (Get $get) => $get('cpu') == 0)
                                                    ->live()
                                                    ->options([
                                                        true => 'Unlimited',
                                                        false => 'Limited',
                                                    ])
                                                    ->colors([
                                                        true => 'primary',
                                                        false => 'warning',
                                                    ])
                                                    ->columnSpan(2),

                                                TextInput::make('cpu')
                                                    ->dehydratedWhenHidden()
                                                    ->hidden(fn (Get $get) => $get('unlimited_cpu'))
                                                    ->label('CPU Limit')->inlineLabel()
                                                    ->suffix('%')
                                                    ->required()
                                                    ->columnSpan(2)
                                                    ->numeric()
                                                    ->minValue(0),
                                            ]),

                                        Grid::make()
                                            ->columns(4)
                                            ->columnSpanFull()
                                            ->schema([
                                                ToggleButtons::make('swap_support')
                                                    ->live()
                                                    ->label('Enable Swap Memory')->inlineLabel()->inline()
                                                    ->columnSpan(2)
                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                        $value = match ($state) {
                                                            'unlimited' => -1,
                                                            'disabled' => 0,
                                                            'limited' => 128,
                                                            default => throw new LogicException('Invalid state')
                                                        };

                                                        $set('swap', $value);
                                                    })
                                                    ->formatStateUsing(function (Get $get) {
                                                        return match (true) {
                                                            $get('swap') > 0 => 'limited',
                                                            $get('swap') == 0 => 'disabled',
                                                            $get('swap') < 0 => 'unlimited',
                                                            default => throw new LogicException('Invalid state')
                                                        };
                                                    })
                                                    ->options([
                                                        'unlimited' => 'Unlimited',
                                                        'limited' => 'Limited',
                                                        'disabled' => 'Disabled',
                                                    ])
                                                    ->colors([
                                                        'unlimited' => 'primary',
                                                        'limited' => 'warning',
                                                        'disabled' => 'danger',
                                                    ]),

                                                TextInput::make('swap')
                                                    ->dehydratedWhenHidden()
                                                    ->hidden(fn (Get $get) => match ($get('swap_support')) {
                                                        'disabled', 'unlimited', true => true,
                                                        default => false,
                                                    })
                                                    ->label('Swap Memory')->inlineLabel()
                                                    ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                                    ->minValue(-1)
                                                    ->columnSpan(2)
                                                    ->required()
                                                    ->integer(),
                                            ]),

                                        Forms\Components\Hidden::make('io')
                                            ->helperText('The IO performance relative to other running containers')
                                            ->label('Block IO Proportion'),

                                        Grid::make()
                                            ->columns(4)
                                            ->columnSpanFull()
                                            ->schema([
                                                ToggleButtons::make('oom_killer')
                                                    ->label('OOM Killer')->inlineLabel()->inline()
                                                    ->columnSpan(2)
                                                    ->options([
                                                        false => 'Disabled',
                                                        true => 'Enabled',
                                                    ])
                                                    ->colors([
                                                        false => 'success',
                                                        true => 'danger',
                                                    ]),

                                                TextInput::make('oom_disabled_hidden')
                                                    ->hidden(),
                                            ]),
                                    ]),

                                Fieldset::make('Feature Limits')
                                    ->inlineLabel()
                                    ->columns([
                                        'default' => 1,
                                        'sm' => 2,
                                        'md' => 3,
                                        'lg' => 3,
                                    ])
                                    ->schema([
                                        TextInput::make('allocation_limit')
                                            ->suffixIcon('tabler-network')
                                            ->required()
                                            ->minValue(0)
                                            ->numeric(),
                                        TextInput::make('database_limit')
                                            ->suffixIcon('tabler-database')
                                            ->required()
                                            ->minValue(0)
                                            ->numeric(),
                                        TextInput::make('backup_limit')
                                            ->suffixIcon('tabler-copy-check')
                                            ->required()
                                            ->minValue(0)
                                            ->numeric(),
                                    ]),
                                Fieldset::make('Docker Settings')
                                    ->columns([
                                        'default' => 1,
                                        'sm' => 2,
                                        'md' => 3,
                                        'lg' => 3,
                                    ])
                                    ->schema([
                                        Select::make('select_image')
                                            ->label('Image Name')
                                            ->afterStateUpdated(fn (Set $set, $state) => $set('image', $state))
                                            ->options(function ($state, Get $get, Set $set) {
                                                $egg = Egg::query()->find($get('egg_id'));
                                                $images = $egg->docker_images ?? [];

                                                $currentImage = $get('image');
                                                if (!$currentImage && $images) {
                                                    $defaultImage = collect($images)->first();
                                                    $set('image', $defaultImage);
                                                    $set('select_image', $defaultImage);
                                                }

                                                return array_flip($images) + ['ghcr.io/custom-image' => 'Custom Image'];
                                            })
                                            ->selectablePlaceholder(false)
                                            ->columnSpan(1),

                                        TextInput::make('image')
                                            ->label('Image')
                                            ->debounce(500)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $egg = Egg::query()->find($get('egg_id'));
                                                $images = $egg->docker_images ?? [];

                                                if (in_array($state, $images)) {
                                                    $set('select_image', $state);
                                                } else {
                                                    $set('select_image', 'ghcr.io/custom-image');
                                                }
                                            })
                                            ->placeholder('Enter a custom Image')
                                            ->columnSpan(2),

                                        Forms\Components\KeyValue::make('docker_labels')
                                            ->label('Container Labels')
                                            ->keyLabel('Label Name')
                                            ->valueLabel('Label Description')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Egg')
                            ->icon('tabler-egg')
                            ->columns([
                                'default' => 1,
                                'sm' => 3,
                                'md' => 3,
                                'lg' => 5,
                            ])
                            ->schema([
                                Select::make('egg_id')
                                    ->disabledOn('edit')
                                    ->prefixIcon('tabler-egg')
                                    ->columnSpan([
                                        'default' => 6,
                                        'sm' => 3,
                                        'md' => 3,
                                        'lg' => 4,
                                    ])
                                    ->relationship('egg', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                ToggleButtons::make('skip_scripts')
                                    ->label('Run Egg Install Script?')->inline()
                                    ->columnSpan([
                                        'default' => 6,
                                        'sm' => 1,
                                        'md' => 1,
                                        'lg' => 2,
                                    ])
                                    ->options([
                                        false => 'Yes',
                                        true => 'Skip',
                                    ])
                                    ->colors([
                                        false => 'primary',
                                        true => 'danger',
                                    ])
                                    ->icons([
                                        false => 'tabler-code',
                                        true => 'tabler-code-off',
                                    ])
                                    ->required(),

                                Forms\Components\TagsInput::make('ports')
                                    ->columnSpan(3)
                                    ->placeholder('Example: 25565, 8080, 1337-1340')
                                    ->splitKeys(['Tab', ' ', ','])
                                    ->helperText(new HtmlString('
                                        These are the ports that users can connect to this Server through.
                                        <br />
                                        You would typically port forward these on your home network.
                                    '))
                                    ->label('Ports')
                                    ->formatStateUsing(fn (Server $server) => $server->ports->map(fn ($port) => (string) $port)->all())
                                    ->afterStateUpdated(self::ports(...))
                                    ->live(),

                                Forms\Components\Repeater::make('portVariables')
                                    ->label('Port Assignments')
                                    ->columnSpan(3)
                                    ->addable(false)
                                    ->deletable(false)

                                    ->mutateRelationshipDataBeforeSaveUsing(function ($data) {
                                        $portIndex = $data['port'];
                                        unset($data['port']);

                                        return [
                                            'variable_value' => (string) $this->ports[$portIndex],
                                        ];
                                    })

                                    ->relationship('serverVariables', function (Builder $query) {
                                        $query->whereHas('variable', function (Builder $query) {
                                            $query->where('rules', 'like', '%port%');
                                        });
                                    })

                                    ->simple(
                                        Forms\Components\Select::make('port')
                                            ->live()
                                            ->disabled(fn (Forms\Get $get) => empty($get('../../ports')) || empty($get('../../assignments')))
                                            ->prefix(function (Forms\Components\Component $component, ServerVariable $serverVariable) {
                                                return $serverVariable->variable->env_variable;
                                            })

                                            ->formatStateUsing(function (ServerVariable $serverVariable, Forms\Get $get) {
                                                return array_search($serverVariable->variable_value, array_values($get('../../ports')));
                                            })

                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->options(fn (Forms\Get $get) => $this->ports)
                                            ->required(),
                                    )

                                    ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, Server $server) {
                                        $this->ports($ports = $get('ports'), $set);

                                        foreach ($this->portOptions($server->egg) as $key => $port) {
                                            $set("assignments.$key", ['port' => $portIndex = array_search($port, array_values($ports))]);
                                        }
                                    }),

                                Textarea::make('startup')
                                    ->label('Startup Command')
                                    ->required()
                                    ->columnSpan(6)
                                    ->rows(function ($state) {
                                        return str($state)->explode("\n")->reduce(
                                            fn (int $carry, $line) => $carry + floor(strlen($line) / 125),
                                            0
                                        );
                                    }),

                                Textarea::make('defaultStartup')
                                    ->hintAction(CopyAction::make())
                                    ->label('Default Startup Command')
                                    ->disabled()
                                    ->formatStateUsing(function ($state, Get $get) {
                                        $egg = Egg::query()->find($get('egg_id'));

                                        return $egg->startup;
                                    })
                                    ->columnSpan(6),

                                Repeater::make('server_variables')
                                    ->relationship('serverVariables', function (Builder $query) {
                                        /** @var Server $server */
                                        $server = $this->getRecord();

                                        foreach ($server->variables as $variable) {
                                            ServerVariable::query()->firstOrCreate([
                                                'server_id' => $server->id,
                                                'variable_id' => $variable->id,
                                            ], [
                                                'variable_value' => $variable->server_value ?? '',
                                            ]);
                                        }

                                        return $query;
                                    })
                                    ->grid()
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array &$data): array {
                                        foreach ($data as $key => $value) {
                                            if (!isset($data['variable_value'])) {
                                                $data['variable_value'] = '';
                                            }
                                        }

                                        return $data;
                                    })
                                    ->reorderable(false)->addable(false)->deletable(false)
                                    ->schema(function () {

                                        $text = TextInput::make('variable_value')
                                            ->hidden($this->shouldHideComponent(...))
                                            ->required(fn (ServerVariable $serverVariable) => $serverVariable->variable->getRequiredAttribute())
                                            ->rules([
                                                fn (ServerVariable $serverVariable): Closure => function (string $attribute, $value, Closure $fail) use ($serverVariable) {
                                                    $validator = Validator::make(['validatorkey' => $value], [
                                                        'validatorkey' => $serverVariable->variable->rules,
                                                    ]);

                                                    if ($validator->fails()) {
                                                        $message = str($validator->errors()->first())->replace('validatorkey', $serverVariable->variable->name);

                                                        $fail($message);
                                                    }
                                                },
                                            ]);

                                        $select = Select::make('variable_value')
                                            ->hidden($this->shouldHideComponent(...))
                                            ->options($this->getSelectOptionsFromRules(...))
                                            ->selectablePlaceholder(false);

                                        $components = [$text, $select];

                                        foreach ($components as &$component) {
                                            $component = $component
                                                ->live(onBlur: true)
                                                ->hintIcon('tabler-code')
                                                ->label(fn (ServerVariable $serverVariable) => $serverVariable->variable->name)
                                                ->hintIconTooltip(fn (ServerVariable $serverVariable) => implode('|', $serverVariable->variable->rules))
                                                ->prefix(fn (ServerVariable $serverVariable) => '{{' . $serverVariable->variable->env_variable . '}}')
                                                ->helperText(fn (ServerVariable $serverVariable) => empty($serverVariable->variable?->description) ? '—' : $serverVariable->variable->description);
                                        }

                                        return $components;
                                    })
                                    ->columnSpan(6),
                            ]),
                        Tab::make('Mounts')
                            ->icon('tabler-layers-linked')
                            ->schema([
                                CheckboxList::make('mounts')
                                    ->relationship('mounts')
                                    ->options(fn (Server $server) => $server->node->mounts->mapWithKeys(fn ($mount) => [$mount->id => $mount->name]))
                                    ->descriptions(fn (Server $server) => $server->node->mounts->mapWithKeys(fn ($mount) => [$mount->id => "$mount->source -> $mount->target"]))
                                    ->label('Mounts')
                                    ->helperText(fn (Server $server) => $server->node->mounts->isNotEmpty() ? '' : 'No Mounts exist for this Node')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Databases')
                            ->icon('tabler-database')
                            ->schema([
                                Repeater::make('databases')
                                    ->grid()
                                    ->helperText(fn (Server $server) => $server->databases->isNotEmpty() ? '' : 'No Databases exist for this Server')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('database')
                                            ->columnSpan(2)
                                            ->label('Database Name')
                                            ->disabled()
                                            ->formatStateUsing(fn ($record) => $record->database)
                                            ->hintAction(
                                                Action::make('Delete')
                                                    ->color('danger')
                                                    ->icon('tabler-trash')
                                                    ->action(fn (DatabaseManagementService $databaseManagementService, $record) => $databaseManagementService->delete($record))
                                            ),
                                        TextInput::make('username')
                                            ->disabled()
                                            ->formatStateUsing(fn ($record) => $record->username)
                                            ->columnSpan(2),
                                        TextInput::make('password')
                                            ->disabled()
                                            ->hintAction(
                                                Action::make('rotate')
                                                    ->icon('tabler-refresh')
                                                    ->requiresConfirmation()
                                                    ->action(fn (DatabasePasswordService $service, $record, $set, $get) => $this->rotatePassword($service, $record, $set, $get))
                                            )
                                            ->formatStateUsing(fn (Database $database) => $database->password)
                                            ->columnSpan(2),
                                        TextInput::make('remote')
                                            ->disabled()
                                            ->formatStateUsing(fn ($record) => $record->remote)
                                            ->columnSpan(1)
                                            ->label('Connections From'),
                                        TextInput::make('max_connections')
                                            ->disabled()
                                            ->formatStateUsing(fn ($record) => $record->max_connections)
                                            ->columnSpan(1),
                                        TextInput::make('JDBC')
                                            ->disabled()
                                            ->label('JDBC Connection String')
                                            ->columnSpan(2)
                                            ->formatStateUsing(fn (Get $get, $record) => 'jdbc:mysql://' . $get('username') . ':' . urlencode($record->password) . '@' . $record->host->host . ':' . $record->host->port . '/' . $get('database')),
                                    ])
                                    ->relationship('databases')
                                    ->deletable(false)
                                    ->addable(false)
                                    ->columnSpan(4),
                            ])->columns(4),
                        Tab::make('Actions')
                            ->icon('tabler-settings')
                            ->schema([
                                Fieldset::make('Server Actions')
                                    ->columns([
                                        'default' => 1,
                                        'sm' => 2,
                                        'md' => 2,
                                        'lg' => 6,
                                    ])
                                    ->schema([
                                        Grid::make()
                                            ->columnSpan(3)
                                            ->schema([
                                                Forms\Components\Actions::make([
                                                    Action::make('toggleInstall')
                                                        ->label('Toggle Install Status')
                                                        ->disabled(fn (Server $server) => $server->isSuspended())
                                                        ->action(function (ServersController $serversController, Server $server) {
                                                            $serversController->toggleInstall($server);

                                                            $this->refreshFormData(['status', 'docker']);
                                                        }),
                                                ])->fullWidth(),
                                                ToggleButtons::make('')
                                                    ->hint('If you need to change the install status from uninstalled to installed, or vice versa, you may do so with this button.'),
                                            ]),
                                        Grid::make()
                                            ->columnSpan(3)
                                            ->schema([
                                                Forms\Components\Actions::make([
                                                    Action::make('toggleSuspend')
                                                        ->label('Suspend')
                                                        ->color('warning')
                                                        ->hidden(fn (Server $server) => $server->isSuspended())
                                                        ->action(function (SuspensionService $suspensionService, Server $server) {
                                                            $suspensionService->toggle($server, 'suspend');
                                                            Notification::make()->success()->title('Server Suspended!')->send();

                                                            $this->refreshFormData(['status', 'docker']);
                                                        }),
                                                    Action::make('toggleUnsuspend')
                                                        ->label('Unsuspend')
                                                        ->color('success')
                                                        ->hidden(fn (Server $server) => !$server->isSuspended())
                                                        ->action(function (SuspensionService $suspensionService, Server $server) {
                                                            $suspensionService->toggle($server, 'unsuspend');
                                                            Notification::make()->success()->title('Server Unsuspended!')->send();

                                                            $this->refreshFormData(['status', 'docker']);
                                                        }),
                                                ])->fullWidth(),
                                                ToggleButtons::make('')
                                                    ->hidden(fn (Server $server) => $server->isSuspended())
                                                    ->hint('This will suspend the server, stop any running processes, and immediately block the user from being able to access their files or otherwise manage the server through the panel or API.'),
                                                ToggleButtons::make('')
                                                    ->hidden(fn (Server $server) => !$server->isSuspended())
                                                    ->hint('This will unsuspend the server and restore normal user access.'),
                                            ]),
                                        Grid::make()
                                            ->columnSpan(3)
                                            ->schema([
                                                Forms\Components\Actions::make([
                                                    Action::make('transfer')
                                                        ->label('Transfer Soon™')
                                                        ->action(fn (TransferServerService $transfer, Server $server) => $transfer->handle($server, []))
                                                        ->disabled() //TODO!
                                                        ->form([ //TODO!
                                                            Select::make('newNode')
                                                                ->label('New Node')
                                                                ->required()
                                                                ->options([
                                                                    true => 'on',
                                                                    false => 'off',
                                                                ]),
                                                            Select::make('newMainAllocation')
                                                                ->label('New Main Allocation')
                                                                ->required()
                                                                ->options([
                                                                    true => 'on',
                                                                    false => 'off',
                                                                ]),
                                                            Select::make('newAdditionalAllocation')
                                                                ->label('New Additional Allocations')
                                                                ->options([
                                                                    true => 'on',
                                                                    false => 'off',
                                                                ]),
                                                        ])
                                                        ->modalHeading('Transfer'),
                                                ])->fullWidth(),
                                                ToggleButtons::make('')
                                                    ->hint('Transfer this server to another node connected to this panel. Warning! This feature has not been fully tested and may have bugs.'),
                                            ]),
                                        Grid::make()
                                            ->columnSpan(3)
                                            ->schema([
                                                Forms\Components\Actions::make([
                                                    Action::make('reinstall')
                                                        ->label('Reinstall')
                                                        ->color('danger')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Are you sure you want to reinstall this server?')
                                                        ->modalDescription('!! This can result in unrecoverable data loss !!')
                                                        ->disabled(fn (Server $server) => $server->isSuspended())
                                                        ->action(fn (ServersController $serversController, Server $server) => $serversController->reinstallServer($server)),
                                                ])->fullWidth(),
                                                ToggleButtons::make('')
                                                    ->hint('This will reinstall the server with the assigned egg install script.'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function transferServer(Form $form): Form
    {
        return $form
            ->columns()
            ->schema([
                Select::make('toNode')
                    ->label('New Node'),
                TextInput::make('newAllocation')
                    ->label('Allocation'),
            ]);

    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Delete')
                ->successRedirectUrl(route('filament.admin.resources.servers.index'))
                ->color('danger')
                ->label('Delete')
                ->requiresConfirmation()
                ->action(function (Server $server, ServerDeletionService $service) {
                    $service->handle($server);

                    return redirect(ListServers::getUrl());
                })
                ->authorize(fn (Server $server) => auth()->user()->can('delete server', $server)),
            Actions\Action::make('console')
                ->label('Console')
                ->icon('tabler-terminal')
                ->url(fn (Server $server) => "/server/$server->uuid_short"),
            $this->getSaveFormAction()->formId('form'),
        ];

    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!isset($data['description'])) {
            $data['description'] = '';
        }

        unset($data['docker'], $data['status']);

        return $data;
    }

    private function shouldHideComponent(ServerVariable $serverVariable, Forms\Components\Component $component): bool
    {
        $containsRuleIn = array_first($serverVariable->variable->rules, fn ($value) => str($value)->startsWith('in:'), false);

        if (collect($serverVariable->variable->rules)->contains('port')) {
            return true;
        }

        if ($component instanceof Select) {
            return !$containsRuleIn;
        }

        if ($component instanceof TextInput) {
            return $containsRuleIn;
        }

        throw new Exception('Component type not supported: ' . $component::class);
    }

    private function getSelectOptionsFromRules(ServerVariable $serverVariable): array
    {
        $inRule = array_first($serverVariable->variable->rules, fn ($value) => str($value)->startsWith('in:'));

        return str($inRule)
            ->after('in:')
            ->explode(',')
            ->each(fn ($value) => str($value)->trim())
            ->mapWithKeys(fn ($value) => [$value => $value])
            ->all();
    }

    public function ports($state, Forms\Set $set)
    {
        $ports = collect();

        foreach ($state as $portEntry) {
            if (str_contains($portEntry, '-')) {
                [$start, $end] = explode('-', $portEntry);

                try {
                    $startEndpoint = new Endpoint($start);
                    $endEndpoint = new Endpoint($end);
                } catch (Exception) {
                    continue;
                }

                if ($startEndpoint->ip !== $endEndpoint->ip) {
                    continue;
                }

                foreach (range($startEndpoint->port, $endEndpoint->port) as $port) {
                    $ports->push(new Endpoint($port, $startEndpoint->ip));
                }

                for ($i = $start; $i <= $end; $i++) {
                    $ports->push($i);
                }

                continue;
            }

            try {
                $ports->push(new Endpoint($portEntry));
            } catch (Exception) {
                continue;
            }
        }

        $ports = $ports->map(fn ($endpoint) => (string) $endpoint);

        $uniquePorts = $ports->unique()->values();
        if ($ports->count() > $uniquePorts->count()) {
            $ports = $uniquePorts;
        }

        $set('ports', $ports->all());
        $this->ports = $ports->all();
    }

    public function portOptions(Egg $egg, string $startup = null): array
    {
        if (empty($startup)) {
            $startup = $egg->startup;
        }

        $options = [];
        if (str_contains($startup, '{{SERVER_PORT}}')) {
            $options['SERVER_PORT'] = null;
        }

        foreach ($egg->variables as $variable) {
            if (!in_array('port', $variable->rules)) {
                continue;
            }

            $options[$variable->env_variable] = $variable->default_value;
        }

        return $options;
    }

    protected function rotatePassword(DatabasePasswordService $service, Database $record, Set $set, Get $get): void
    {
        $newPassword = $service->handle($record);
        $jdbcString = 'jdbc:mysql://' . $get('username') . ':' . urlencode($newPassword) . '@' . $record->host->host . ':' . $record->host->port . '/' . $get('database');

        $set('password', $newPassword);
        $set('JDBC', $jdbcString);
    }
}
