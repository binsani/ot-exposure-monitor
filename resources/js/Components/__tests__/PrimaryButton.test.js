import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PrimaryButton from '../PrimaryButton.vue';

describe('PrimaryButton', () => {
    it('renders accessible button content', () => {
        const wrapper = mount(PrimaryButton, { slots: { default: 'Save asset' } });
        expect(wrapper.get('button').text()).toBe('Save asset');
    });
});
