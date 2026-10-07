import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import Stream from 'flarum/common/utils/Stream';
import mq from 'mithril-query';
import SampleRateSlider from '../../../src/admin/components/SampleRateSlider';

beforeAll(() => bootstrapAdmin());

describe('SampleRateSlider', () => {
  it('renders the label, the current value and the range bounds', () => {
    const slider = mq(SampleRateSlider, { value: Stream('25'), label: 'Trace rate', help: 'Some help' });

    expect(slider).toContainRaw('Trace rate');
    expect(slider).toHaveElement('.SampleRateSlider-value');
    expect(slider.first('.SampleRateSlider-value').textContent).toBe('25%');
    expect(slider.first('.SampleRateSlider-label-min').textContent).toBe('0%');
    expect(slider.first('.SampleRateSlider-label-max').textContent).toBe('100%');
    expect(slider.first('.SampleRateSlider-fill').getAttribute('style')).toMatch(/width: 25%/);
    expect(slider).toContainRaw('Some help');
  });

  it('treats an empty or invalid value as 0', () => {
    const slider = mq(SampleRateSlider, { value: Stream(''), label: 'Rate' });

    expect(slider.first('.SampleRateSlider-value').textContent).toBe('0%');
  });

  it('writes input changes back to the stream', () => {
    const value = Stream('10');
    const slider = mq(SampleRateSlider, { value, label: 'Rate' });

    slider.setValue('input.SampleRateSlider-input', '60');

    expect(value()).toBe('60');
  });

  it('can be disabled', () => {
    const slider = mq(SampleRateSlider, { value: Stream('10'), label: 'Rate', disabled: true });

    expect(slider).toHaveElement('input.SampleRateSlider-input[disabled]');
  });
});
