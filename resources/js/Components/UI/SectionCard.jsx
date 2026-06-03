/**
 * SectionCard — Premium content card with an optional header.
 * Used throughout the app as a consistent white card container.
 */
import React from 'react';
import { T } from '../../design/tokens';

/**
 * @param {string}      title      Card header title
 * @param {string}      subtitle   Muted description under the title
 * @param {ReactNode}   action     Element rendered on the right side of the header
 * @param {ReactNode}   children   Card body content
 * @param {object}      style      Extra styles for the outer wrapper
 * @param {object}      bodyStyle  Extra styles for the body wrapper
 */
export default function SectionCard({
    title,
    subtitle,
    action,
    children,
    style = {},
    bodyStyle = {},
}) {
    return (
        <div style={{
            background:   T.surface,
            borderRadius: T.radius,
            border:       `1px solid ${T.border}`,
            boxShadow:    T.shadow,
            overflow:     'hidden',
            ...style,
        }}>
            {(title || action) && (
                <div style={{
                    padding:        '14px 20px',
                    borderBottom:   `1px solid ${T.borderSoft}`,
                    background:     '#fafbfc',
                    display:        'flex',
                    alignItems:     'center',
                    justifyContent: 'space-between',
                    gap:            '12px',
                }}>
                    <div>
                        <div style={{
                            fontSize:   '13.5px',
                            fontWeight: '600',
                            color:      T.text,
                            lineHeight: '1.4',
                        }}>
                            {title}
                        </div>
                        {subtitle && (
                            <div style={{
                                fontSize:   '12px',
                                color:      T.textMuted,
                                marginTop:  '2px',
                                lineHeight: '1.3',
                            }}>
                                {subtitle}
                            </div>
                        )}
                    </div>
                    {action}
                </div>
            )}
            <div style={bodyStyle}>{children}</div>
        </div>
    );
}
