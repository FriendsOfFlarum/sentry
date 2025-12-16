import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import Stream from 'flarum/common/utils/Stream';

export interface SampleRateSliderAttrs extends Mithril.Attributes {
  value: Stream<string>;
  label: string;
  help?: Mithril.Children;
  min?: number;
  max?: number;
  step?: number;
  disabled?: boolean;
}

export default class SampleRateSlider extends Component<SampleRateSliderAttrs> {
  view() {
    const { label, help, value, min = 0, max = 100, step = 1, disabled = false } = this.attrs;
    const currentValue = Number(value()) || 0;

    return (
      <div className="Form-group SampleRateSlider">
        <label>
          {label}
          <span className="SampleRateSlider-value">{currentValue}%</span>
        </label>
        {help && <div className="helpText">{help}</div>}
        <div className="SampleRateSlider-container">
          <input
            type="range"
            className="SampleRateSlider-input"
            min={min}
            max={max}
            step={step}
            value={currentValue}
            disabled={disabled}
            oninput={(e: InputEvent) => {
              const target = e.target as HTMLInputElement;
              value(target.value);
            }}
          />
          <div className="SampleRateSlider-track">
            <div className="SampleRateSlider-fill" style={`width: ${currentValue}%`} />
          </div>
          <div className="SampleRateSlider-labels">
            <span className="SampleRateSlider-label-min">{min}%</span>
            <span className="SampleRateSlider-label-max">{max}%</span>
          </div>
        </div>
      </div>
    );
  }
}
