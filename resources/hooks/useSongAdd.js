import { useNavigate } from "react-router";
import { apiUrl } from "../js/App";
import { useParams } from "react-router-dom";
import { useState } from "react";

export const useSongAdd = (
    song,
    user,
    setOpen,
    setModalMessage,
    setModalHeader
) => {
    let navigate = useNavigate();
    const { id } = useParams();
    const spotifyId = song.spotify_id;
    const [isAdded, setIsAdded] = useState(song.is_requested);
    const [isLoading, setIsLoading] = useState(false);
    const intialStateIsAdded = isAdded;

    const addSong = () => {
        if (!user) {
            navigate("/login");
        }

        if (song.is_requested) {
            setModalHeader("Oooooups!");
            setModalMessage("Cette chanson a été déjà suggérée!");
            setOpen(true);
            return;
        }

        if (isLoading) return;

        setIsLoading(true);

        axios
            .post(`${apiUrl}/${id}/songs/${spotifyId}/store`, [
                {
                    spotifyId: spotifyId,
                },
            ])
            .then((response) => {
                if (response.data.status == "added") {
                    setModalHeader("BRAVO!");
                    setModalMessage(response.data.message);
                    setOpen(true);
                }
                if (response.data.status == "deleted") {
                    setModalHeader("C'EST FAIT!");
                    setModalMessage(response.data.message);
                    setOpen(true);
                }
            })
            .catch((error) => {
                if (error.response.data.error == "forbidden") {
                    setIsAdded(isAdded);
                    setModalHeader("Oooooups!");
                    setModalMessage(error.response.data.message);
                    setOpen(true);
                }
                setIsAdded(intialStateIsAdded);
            })
            .finally(() => setIsLoading(false));

        setIsAdded(!isAdded);
    };

    return {
        addSong,
        isAdded,
    };
};
