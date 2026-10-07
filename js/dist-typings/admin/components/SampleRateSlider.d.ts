import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import Stream from 'flarum/common/utils/Stream';
/** The `type` to use for a sample-rate setting registered through `Extend.Admin().setting()`. */
export declare const SAMPLE_RATE_FIELD = "fof-sentry.sample-rate";
export interface SampleRateSliderAttrs extends Mithril.Attributes {
    value: Stream<string>;
    label: Mithril.Children;
    help?: Mithril.Children;
    min?: number;
    max?: number;
    step?: number;
    disabled?: boolean;
}
export default class SampleRateSlider extends Component<SampleRateSliderAttrs> {
    view(): JSX.Element;
}
