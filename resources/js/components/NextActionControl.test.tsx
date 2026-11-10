import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { NextAction } from '../types';
import { NextActionControl } from './NextActionControl';

function setup(action: NextAction | null, disabled = false) {
    const handlers = { onAssign: vi.fn(), onStart: vi.fn(), onComplete: vi.fn() };
    const view = render(<NextActionControl action={action} disabled={disabled} {...handlers} />);

    return { ...handlers, ...view };
}

describe('NextActionControl', () => {
    it('UI-301 offers a name input and an assign button for a pending inspection, and nothing else', () => {
        setup('assign');

        expect(screen.getByLabelText('Inspector name')).toBeTruthy();
        expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
            'Assign inspector',
        ]);
    });

    it('UI-301 keeps the assign button disabled until the name has a character that is not a space', async () => {
        setup('assign');
        const button = screen.getByRole('button', {
            name: 'Assign inspector',
        }) as HTMLButtonElement;

        expect(button.disabled).toBe(true);
        await userEvent.type(screen.getByLabelText('Inspector name'), '   ');
        expect(button.disabled).toBe(true);
        await userEvent.type(screen.getByLabelText('Inspector name'), 'S');
        expect(button.disabled).toBe(false);
    });

    it('UI-301 sends the name without the spaces around it', async () => {
        const { onAssign, onStart, onComplete } = setup('assign');

        await userEvent.type(screen.getByLabelText('Inspector name'), '  Sam Carter  ');
        await userEvent.click(screen.getByRole('button', { name: 'Assign inspector' }));

        expect(onAssign).toHaveBeenCalledTimes(1);
        expect(onAssign).toHaveBeenCalledWith('Sam Carter');
        expect(onStart).not.toHaveBeenCalled();
        expect(onComplete).not.toHaveBeenCalled();
    });

    it('UI-302 offers only the start button for an assigned inspection', async () => {
        const { onAssign, onStart, onComplete } = setup('start');

        expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
            'Start inspection',
        ]);
        await userEvent.click(screen.getByRole('button', { name: 'Start inspection' }));

        expect(onStart).toHaveBeenCalledTimes(1);
        expect(onAssign).not.toHaveBeenCalled();
        expect(onComplete).not.toHaveBeenCalled();
    });

    it('UI-303 offers only the complete button for an inspection in progress', async () => {
        const { onAssign, onStart, onComplete } = setup('complete');

        expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
            'Complete inspection',
        ]);
        await userEvent.click(screen.getByRole('button', { name: 'Complete inspection' }));

        expect(onComplete).toHaveBeenCalledTimes(1);
        expect(onAssign).not.toHaveBeenCalled();
        expect(onStart).not.toHaveBeenCalled();
    });

    it('UI-303 offers nothing when there is no next action', () => {
        const { container } = setup(null);

        expect(container.innerHTML).toBe('');
    });

    it('cannot be used while an action is pending', async () => {
        const { onStart } = setup('start', true);

        await userEvent.click(screen.getByRole('button', { name: 'Start inspection' }));

        expect(onStart).not.toHaveBeenCalled();
    });
});
