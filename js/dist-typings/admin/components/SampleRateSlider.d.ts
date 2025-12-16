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
    view(): JSX.Element;
}
