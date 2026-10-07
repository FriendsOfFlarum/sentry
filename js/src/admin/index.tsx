import app from 'flarum/admin/app';
import { extend as extendPrototype } from 'flarum/common/extend';
import FormGroup from 'flarum/common/components/FormGroup';
import SampleRateSlider, { SAMPLE_RATE_FIELD } from './components/SampleRateSlider';

export { default as extend } from './extend';

app.initializers.add('fof/sentry', () => {
  extendPrototype(FormGroup.prototype, 'customFieldComponents', function (items) {
    items.add(SAMPLE_RATE_FIELD, (attrs) => (
      <SampleRateSlider
        value={attrs.bidi}
        label={attrs.label}
        help={attrs.help}
        min={attrs.min}
        max={attrs.max}
        step={attrs.step}
        disabled={attrs.disabled}
      />
    ));
  });
});
