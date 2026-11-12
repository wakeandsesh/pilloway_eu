export const DivisionItem = ({ item }) => {
    return (
        <div>
            <strong>{item.label}</strong> <br />
            <span>{item.address}</span>
        </div>
    );
};
