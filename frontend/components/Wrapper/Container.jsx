import Header from '@components/Header';
import Footer from '@components/Footer';

const Container = ({children}) => {
    return (
        <div className="min-h-screen bg-gradient-to-b from-white to-gray-100 flex flex-col">
            <Header/>
            {children}
            <Footer/>
        </div>
    );
}

export default Container;