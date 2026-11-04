# Custom steps

| Interface | Behaviour |
|---|---|
| `DumpLoader\Step\Step` | Always runs |
| `DumpLoader\Step\OptionalStep` | Checkbox in the UI, its name is the option key |
| `DumpLoader\Step\ParameterizedStep` | Optional step asking for [inputs](#inputs) when it is checked |

Autoconfigured. `rank` places the step (built-in: 50, 100, 200, 300), `weight` is its share of the
overall progress.

```php
namespace App\DumpLoader;

use DumpLoader\LoadJob\LogTag;
use DumpLoader\LoadJob\Percentage;
use DumpLoader\Step\LoadContext;
use DumpLoader\Step\OptionalStep;
use DumpLoader\Step\StepDefinition;
use DumpLoader\Step\StepTracker;

final readonly class AnonymizeCustomers implements OptionalStep
{
    public function __construct(private Connection $connection)
    {
    }

    public function definition(): StepDefinition
    {
        return new StepDefinition(
            name: 'anonymize_customers',
            label: 'step.anonymize_customers.label',
            description: 'step.anonymize_customers.description',
            rank: 150,
            weight: 2,
        );
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function run(LoadContext $context, StepTracker $tracker): void
    {
        $tracker->log(LogTag::Run, 'Anonymizing customers.');
        $this->connection->executeStatement('UPDATE customer SET email = CONCAT("user", id, "@example.com")');
        $tracker->progress(Percentage::complete());
    }
}
```

- Talk to the `StepTracker`: log, progress, `abortRequested()`.
- Throw from `run()` to fail the job with that message.
- Label and description use the `DumpLoaderBundle` translation domain.

Real anonymization: [`gilles-g/db-anonymizer`](https://github.com/gilles-g/db-anonymizer), YAML
rules, plugged in at rank 250, after migrations.

## Inputs

A `ParameterizedStep` declares what the operator types in when the step is checked: a field under
its checkbox in the UI, `--input=STEP.INPUT=VALUE` or a question on the command line.

```php
use DumpLoader\Step\ParameterizedStep;
use DumpLoader\Step\StepInput;
use DumpLoader\Step\StepInputType;

final readonly class RestrictToInsured implements ParameterizedStep
{
    public function inputs(): array
    {
        return [
            new StepInput('insured_ids', 'Insured to keep', StepInputType::IntList, description: 'Comma-separated ids'),
            new StepInput('reason', 'Reason', required: false),
        ];
    }

    public function run(LoadContext $context, StepTracker $tracker): void
    {
        $insuredIds = $context->options->input('restrict_to_insured', 'insured_ids');
        $tracker->log(LogTag::Run, \sprintf('Keeping %d insured.', \count($insuredIds)));

        if ($context->options->hasInput('restrict_to_insured', 'reason')) {
            $tracker->log(LogTag::Run, $context->options->input('restrict_to_insured', 'reason'));
        }
    }
}
```

| Type | Typed in | Handed to `run()` |
|---|---|---|
| `StepInputType::String` (default) | `ACME` | `'ACME'` |
| `StepInputType::Int` | `42` | `42` |
| `StepInputType::IntList` | `12, 34` | `[12, 34]` |

- The request is refused before anything is dropped when a required input is missing, a value does
  not match its type, or an input is unknown to the checked steps.
- A blank optional input is not entered: check `hasInput()` before `input()`.
- `sensitive: true` hides the field and keeps the value out of the job file, the history, the report
  and the logs. It is held apart until the job starts, then deleted.
- Names: lowercase letters, digits, underscores.

## From YAML

A library ships a `DumpLoader\Step\StepFactory`. Its `configurationKey()` names an entry under
`steps`, `create()` turns that entry into a `Step`.

```yaml
dump_loader:
    steps:
        anonymization:
            seed: '%env(ANONYMIZER_SEED)%'
            tables:
                users:
                    columns:
                        email: { mask: email }
```

An unknown key fails the container build and lists the known ones.
