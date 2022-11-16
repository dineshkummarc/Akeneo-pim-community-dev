import React, {FC, memo} from 'react';
import styled from 'styled-components';
import {getColor, Pill, Table} from 'akeneo-design-system';
import {useTranslate} from '@akeneo-pim-community/shared';
import {SourceLabel} from './SourceLabel';

const TargetCell = styled(Table.Cell)`
    width: 215px;
    color: ${getColor('brand', 100)};
    font-style: italic;
`;
const PlaceholderCell = styled(Table.Cell)`
    color: ${getColor('grey', 100)};
    font-style: italic;
`;
const ErrorPill = styled(Pill)`
    margin-left: 10px;
`;

type Props = {
    targetCode: string;
    targetLabel: string | undefined;
    sourceCode: string | null;
    onClick: (targetCode: string) => void;
    hasError: boolean;
};

export const TargetSourceAssociation: FC<Props> = memo(({targetCode, targetLabel, sourceCode, onClick, hasError}) => {
    const translate = useTranslate();

    return (
        <Table.Row key={targetCode} onClick={() => onClick(targetCode)}>
            <TargetCell>{targetLabel ?? targetCode}</TargetCell>
            {null === sourceCode && (
                <PlaceholderCell>
                    {translate('akeneo_catalogs.product_mapping.target.table.placeholder')}
                </PlaceholderCell>
            )}
            {sourceCode && (
                <Table.Cell>
                    <SourceLabel sourceCode={sourceCode} />
                    {hasError && <ErrorPill data-testid='error-pill' level='danger' />}
                </Table.Cell>
            )}
        </Table.Row>
    );
});
